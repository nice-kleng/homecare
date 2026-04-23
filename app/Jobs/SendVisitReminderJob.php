<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\VisitReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dikirim via Command SendVisitReminders (cron harian jam 18.00).
 * Satu job per order agar gagal satu tidak mempengaruhi yang lain.
 */
class SendVisitReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $backoff = 60;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $order = Order::with([
            'patient.user',
            'service',
            'visits.staff.user',
        ])->find($this->orderId);

        if (! $order) {
            Log::warning("SendVisitReminderJob: Order ID {$this->orderId} tidak ditemukan.");
            return;
        }

        // Skip jika order sudah tidak aktif
        if (! in_array($order->status, ['confirmed', 'assigned'])) {
            return;
        }

        // Kirim ke pasien
        if ($order->patient->user) {
            $order->patient->user->notify(
                new VisitReminderNotification($order, 'patient')
            );
        }

        // Kirim WhatsApp ke pasien
        if ($order->patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $order->patient->phone,
                message: "Pengingat: Kunjungan Anda besok *{$order->visit_date}* "
                         . "pukul *{$order->visit_time_start}*.\n"
                         . "Layanan: {$order->service?->name}.\n"
                         . "Mohon pastikan ada di rumah.",
                event  : 'visit.reminder.patient',
                refId  : $order->id,
                refType: 'order',
            );
        }

        // Kirim ke petugas yang ditugaskan
        $visit = $order->visits->whereIn('status', ['scheduled'])->first();
        if ($visit && $visit->staff) {
            if ($visit->staff->user) {
                $visit->staff->user->notify(
                    new VisitReminderNotification($order, 'staff')
                );
            }

            if ($visit->staff->phone) {
                SendWhatsAppJob::dispatch(
                    phone  : $visit->staff->phone,
                    message: "Pengingat: Anda memiliki kunjungan besok *{$order->visit_date}* "
                             . "pukul *{$order->visit_time_start}*.\n"
                             . "Pasien: {$order->patient->name}.\n"
                             . "Alamat: {$order->visit_address}.",
                    event  : 'visit.reminder.staff',
                    refId  : $order->id,
                    refType: 'order',
                );
            }
        }

        Log::info("Reminder terkirim untuk Order {$order->order_number}.");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendVisitReminderJob gagal. OrderID: {$this->orderId}. Error: {$exception->getMessage()}");
    }
}
