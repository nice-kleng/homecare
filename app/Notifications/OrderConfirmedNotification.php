<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order {$this->order->order_number} Dikonfirmasi")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Order Anda dengan nomor **{$this->order->order_number}** telah dikonfirmasi.")
            ->line("Layanan   : {$this->order->service?->name}")
            ->line("Jadwal    : {$this->order->visit_date} pukul {$this->order->visit_time_start}")
            ->line("Alamat    : {$this->order->visit_address}")
            ->action('Lihat Detail Order', url(route('pasien.orders.show', $this->order)))
            ->line('Petugas kami akan segera kami tugaskan. Terima kasih.')
            ->salutation('Salam, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'order.confirmed',
            'title'        => 'Order Dikonfirmasi',
            'message'      => "Order {$this->order->order_number} telah dikonfirmasi.",
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'url'          => route('pasien.orders.show', $this->order),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
