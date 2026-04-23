<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order  $order,
        public readonly string $recipientType, // 'patient' | 'staff'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->order->visits->last();

        $mail = (new MailMessage)
            ->subject("Pengingat: Kunjungan Besok - {$this->order->order_number}")
            ->greeting("Halo, {$notifiable->name}!");

        if ($this->recipientType === 'staff') {
            $mail->line("Pengingat: Anda memiliki kunjungan besok.")
                 ->line("Pasien  : {$this->order->patient->name}")
                 ->line("Layanan : {$this->order->service?->name}")
                 ->line("Waktu   : {$this->order->visit_time_start}")
                 ->line("Alamat  : {$this->order->visit_address}")
                 ->action('Lihat Detail', url(route('petugas.visits.show', $visit)));
        } else {
            $staff = $visit?->staff?->user;
            $mail->line("Pengingat: Kunjungan Anda dijadwalkan besok.")
                 ->line("Layanan : {$this->order->service?->name}")
                 ->line("Waktu   : {$this->order->visit_time_start}")
                 ->line("Petugas : " . ($staff?->name ?? 'Akan segera dikonfirmasi'))
                 ->line("Mohon pastikan Anda atau keluarga berada di rumah.")
                 ->action('Lihat Detail Order', url(route('pasien.orders.show', $this->order)));
        }

        return $mail->salutation('Salam, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'visit.reminder',
            'title'        => 'Pengingat Kunjungan Besok',
            'message'      => "Kunjungan untuk order {$this->order->order_number} dijadwalkan besok pukul {$this->order->visit_time_start}.",
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'visit_date'   => $this->order->visit_date,
            'visit_time'   => $this->order->visit_time_start,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
