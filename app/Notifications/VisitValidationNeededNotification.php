<?php

namespace App\Notifications;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitValidationNeededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Visit $visit) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->visit->order;

        return (new MailMessage)
            ->subject("[Dokter] Laporan Kunjungan Perlu Divalidasi")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Laporan kunjungan berikut menunggu validasi Anda.")
            ->line("Pasien   : {$order->patient->name}")
            ->line("Layanan  : {$order->service?->name}")
            ->line("Petugas  : {$this->visit->staff?->user?->name}")
            ->line("Selesai  : {$this->visit->completed_at}")
            ->action('Validasi Sekarang', url(route('dokter.visits.show', $this->visit)))
            ->salutation('Sistem Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        $order = $this->visit->order;

        return [
            'type'         => 'visit.needs_validation',
            'title'        => 'Laporan Kunjungan Perlu Divalidasi',
            'message'      => "Laporan kunjungan pasien {$order->patient->name} menunggu validasi Anda.",
            'visit_id'     => $this->visit->id,
            'order_id'     => $order->id,
            'patient_name' => $order->patient->name,
            'url'          => route('dokter.visits.show', $this->visit),
            'priority'     => 'normal',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
