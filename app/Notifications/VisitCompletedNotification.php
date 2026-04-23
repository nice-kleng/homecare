<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->order->visits()->where('status', 'completed')->latest()->first();

        return (new MailMessage)
            ->subject("Kunjungan Selesai - Berikan Penilaian Anda")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Kunjungan untuk layanan **{$this->order->service?->name}** telah selesai.")
            ->line("Kami harap Anda puas dengan pelayanan yang diberikan.")
            ->action('Berikan Penilaian', url(route('pasien.visits.rate', $visit)))
            ->line('Penilaian Anda membantu kami meningkatkan kualitas layanan.')
            ->salutation('Terima kasih, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        $visit = $this->order->visits()->where('status', 'completed')->latest()->first();

        return [
            'type'         => 'visit.completed',
            'title'        => 'Kunjungan Selesai',
            'message'      => "Kunjungan {$this->order->order_number} telah selesai. Berikan penilaian Anda.",
            'order_id'     => $this->order->id,
            'visit_id'     => $visit?->id,
            'order_number' => $this->order->order_number,
            'action'       => 'rate',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
