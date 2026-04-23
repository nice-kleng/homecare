<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderAdminNotification extends Notification implements ShouldQueue
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
            ->subject("[Admin] Order Baru - {$this->order->order_number}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Order baru masuk dan menunggu konfirmasi Anda.")
            ->line("No. Order : **{$this->order->order_number}**")
            ->line("Pasien    : {$this->order->patient->name}")
            ->line("Layanan   : {$this->order->service?->name}")
            ->line("Jadwal    : {$this->order->visit_date} pukul {$this->order->visit_time_start}")
            ->line("Alamat    : {$this->order->visit_address}")
            ->action('Konfirmasi Sekarang', url(route('admin.orders.show', $this->order)))
            ->salutation('Sistem Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'order.created',
            'title'        => 'Order Baru Masuk',
            'message'      => "Order {$this->order->order_number} dari {$this->order->patient->name} menunggu konfirmasi.",
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'patient_name' => $this->order->patient->name,
            'url'          => route('admin.orders.show', $this->order),
            'priority'     => 'high',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
