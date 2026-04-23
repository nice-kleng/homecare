<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order  $order,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order {$this->order->order_number} Dibatalkan")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Order Anda dengan nomor **{$this->order->order_number}** telah dibatalkan.")
            ->line("Alasan: {$this->reason}")
            ->line("Jika Anda merasa ini adalah kesalahan, silakan hubungi kami atau buat order baru.")
            ->action('Buat Order Baru', url(route('pasien.orders.create')))
            ->salutation('Mohon maaf atas ketidaknyamanan ini, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'order.cancelled',
            'title'        => 'Order Dibatalkan',
            'message'      => "Order {$this->order->order_number} dibatalkan. Alasan: {$this->reason}",
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'reason'       => $this->reason,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
