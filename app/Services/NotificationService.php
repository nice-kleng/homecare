<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class NotificationService
{
    /**
     * Kirim notifikasi ke semua admin aktif.
     */
    public function notifyAdmins(string $event, mixed $subject): void
    {
        $admins = User::role('admin')->where('status', 'active')->get();

        foreach ($admins as $admin) {
            $this->dispatch($admin, $event, $subject);
        }
    }

    /**
     * Kirim notifikasi ke pasien terkait order/invoice.
     */
    public function notifyPatient(Patient $patient, string $event, mixed $subject): void
    {
        if ($patient->user) {
            $this->dispatch($patient->user, $event, $subject);
        }

        // Kirim juga via WhatsApp jika nomor tersedia
        if ($patient->phone) {
            $this->sendWhatsApp($patient->phone, $event, $subject);
        }
    }

    /**
     * Kirim notifikasi ke petugas.
     */
    public function notifyStaff(Staff $staff, string $event, mixed $subject): void
    {
        if ($staff->user) {
            $this->dispatch($staff->user, $event, $subject);
        }

        if ($staff->phone) {
            $this->sendWhatsApp($staff->phone, $event, $subject);
        }
    }

    /**
     * Dispatch in-app notification (Laravel Notification).
     */
    private function dispatch(User $user, string $event, mixed $subject): void
    {
        try {
            $notificationClass = $this->resolveNotificationClass($event);

            if ($notificationClass && class_exists($notificationClass)) {
                $user->notify(new $notificationClass($subject));
            }
        } catch (\Throwable $e) {
            Log::warning("NotificationService dispatch failed: {$e->getMessage()}", [
                'event'   => $event,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Kirim pesan WhatsApp via provider (Fonnte / Wablas / dll).
     * Message template diambil dari config/whatsapp.php.
     */
    public function sendWhatsApp(string $phone, string $event, mixed $subject): void
    {
        $message = $this->buildWhatsAppMessage($event, $subject);

        if (empty($message)) {
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('whatsapp.token'),
            ])->post(config('whatsapp.endpoint'), [
                'target'  => $phone,
                'message' => $message,
            ]);

            $this->logNotification($phone, 'whatsapp', $event, $message, $response->successful());
        } catch (\Throwable $e) {
            $this->logNotification($phone, 'whatsapp', $event, $message, false, $e->getMessage());
            Log::error("WhatsApp send failed: {$e->getMessage()}", ['phone' => $phone]);
        }
    }

    /**
     * Build pesan WhatsApp berdasarkan event & data subject.
     */
    private function buildWhatsAppMessage(string $event, mixed $subject): string
    {
        return match ($event) {
            'order.received' => $subject instanceof Order
                ? "Halo {$subject->patient->name}, pesanan Anda *{$subject->order_number}* telah kami terima. "
                  . "Kunjungan dijadwalkan {$subject->visit_date} pukul {$subject->visit_time_start}. "
                  . "Kami akan segera konfirmasi."
                : '',

            'order.confirmed' => $subject instanceof Order
                ? "Pesanan *{$subject->order_number}* telah dikonfirmasi. "
                  . "Petugas akan segera kami tugaskan. Terima kasih."
                : '',

            'visit.assigned' => $subject instanceof Order
                ? "Petugas telah ditugaskan untuk kunjungan Anda pada {$subject->visit_date} "
                  . "pukul {$subject->visit_time_start}."
                : '',

            'visit.on_the_way' => $subject instanceof Order
                ? "Petugas kami sedang dalam perjalanan menuju lokasi Anda. Mohon bersiap."
                : '',

            'visit.completed' => $subject instanceof Order
                ? "Kunjungan selesai. Terima kasih telah mempercayakan perawatan kepada kami. "
                  . "Silakan berikan penilaian layanan Anda."
                : '',

            'invoice.generated' => $subject instanceof Invoice
                ? "Invoice *{$subject->invoice_number}* telah diterbitkan. "
                  . "Total: Rp " . number_format($subject->patient_liability, 0, ',', '.') . ". "
                  . "Jatuh tempo: {$subject->due_date}."
                : '',

            'payment.verified' => $subject instanceof Invoice
                ? "Pembayaran Anda untuk invoice *{$subject->invoice_number}* telah terverifikasi. Terima kasih."
                : '',

            default => '',
        };
    }

    /**
     * Map event ke class Laravel Notification.
     */
    private function resolveNotificationClass(string $event): ?string
    {
        $map = [
            'order.confirmed'    => \App\Notifications\OrderConfirmedNotification::class,
            'visit.assigned'     => \App\Notifications\VisitScheduledNotification::class,
            'visit.completed'    => \App\Notifications\VisitCompletedNotification::class,
            'invoice.generated'  => \App\Notifications\InvoiceGeneratedNotification::class,
            'payment.verified'   => \App\Notifications\PaymentVerifiedNotification::class,
        ];

        return $map[$event] ?? null;
    }

    /**
     * Catat log pengiriman notifikasi ke tabel notification_logs.
     */
    private function logNotification(
        string $recipient,
        string $channel,
        string $event,
        string $message,
        bool   $success,
        string $errorMessage = ''
    ): void {
        try {
            NotificationLog::create([
                'channel'       => $channel,
                'recipient'     => $recipient,
                'subject'       => $event,
                'message'       => $message,
                'status'        => $success ? 'sent' : 'failed',
                'provider'      => config('whatsapp.provider', 'fonnte'),
                'error_message' => $errorMessage ?: null,
                'sent_at'       => $success ? now() : null,
                'notifiable_type'=> '',
                'notifiable_id' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal menyimpan notification log: ' . $e->getMessage());
        }
    }
}
