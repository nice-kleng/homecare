<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit  = $this->order->visits->last();
        $staff  = $visit?->staff?->user;
        $isPetugas = $notifiable->hasRole('petugas');

        $mail = (new MailMessage)
            ->subject("Kunjungan Dijadwalkan - {$this->order->order_number}")
            ->greeting("Halo, {$notifiable->name}!");

        if ($isPetugas) {
            $mail->line("Anda ditugaskan untuk kunjungan berikut:")
                 ->line("Pasien  : {$this->order->patient->name}")
                 ->line("Layanan : {$this->order->service?->name}")
                 ->line("Jadwal  : {$this->order->visit_date} pukul {$this->order->visit_time_start}")
                 ->line("Alamat  : {$this->order->visit_address}")
                 ->action('Lihat Detail Kunjungan', url(route('petugas.visits.show', $visit)));
        } else {
            $mail->line("Petugas telah ditugaskan untuk kunjungan Anda.")
                 ->line("Petugas : " . ($staff?->name ?? '-'))
                 ->line("Jadwal  : {$this->order->visit_date} pukul {$this->order->visit_time_start}")
                 ->action('Lacak Status', url(route('pasien.orders.show', $this->order)));
        }

        return $mail->salutation('Salam, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        $visit = $this->order->visits->last();

        return [
            'type'         => 'visit.scheduled',
            'title'        => 'Kunjungan Dijadwalkan',
            'message'      => "Kunjungan untuk order {$this->order->order_number} telah dijadwalkan.",
            'order_id'     => $this->order->id,
            'visit_id'     => $visit?->id,
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
