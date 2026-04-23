<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\OrderConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPatientOrderConfirmedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderConfirmed $event): void
    {
        $order   = $event->order->load(['patient.user', 'service']);
        $patient = $order->patient;

        // In-app + email notification
        if ($patient->user) {
            $patient->user->notify(new OrderConfirmedNotification($order));
        }

        // WhatsApp notification
        if ($patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $patient->phone,
                message: "Halo {$patient->name},\n\n"
                         . "Order *{$order->order_number}* telah *dikonfirmasi*.\n"
                         . "Layanan : {$order->service?->name}\n"
                         . "Jadwal  : {$order->visit_date} pukul {$order->visit_time_start}\n"
                         . "Petugas akan segera kami tugaskan. Terima kasih.",
                event  : 'order.confirmed',
                refId  : $order->id,
                refType: 'order',
            );
        }
    }
}
