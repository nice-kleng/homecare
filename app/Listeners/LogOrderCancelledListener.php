<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\OrderCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogOrderCancelledListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderCancelled $event): void
    {
        $order  = $event->order->load(['patient.user', 'service', 'visits.staff.user']);
        $reason = $event->reason;

        // Notifikasi ke pasien
        if ($order->patient->user) {
            $order->patient->user->notify(new OrderCancelledNotification($order, $reason));
        }

        if ($order->patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $order->patient->phone,
                message: "Halo {$order->patient->name},\n\n"
                         . "Mohon maaf, order *{$order->order_number}* dibatalkan.\n"
                         . "Alasan : {$reason}\n\n"
                         . "Silakan hubungi kami jika ada pertanyaan.",
                event  : 'order.cancelled',
                refId  : $order->id,
                refType: 'order',
            );
        }

        // Notifikasi ke petugas yang sudah ditugaskan (jika ada)
        $assignedVisit = $order->visits
            ->whereIn('status', ['scheduled', 'on_the_way'])
            ->first();

        if ($assignedVisit && $assignedVisit->staff?->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $assignedVisit->staff->phone,
                message: "Informasi: Kunjungan untuk pasien {$order->patient->name} "
                         . "({$order->visit_date} {$order->visit_time_start}) telah dibatalkan.",
                event  : 'order.cancelled.staff',
                refId  : $order->id,
                refType: 'order',
            );
        }

        Log::info("Order {$order->order_number} dibatalkan. Alasan: {$reason}");
    }
}
