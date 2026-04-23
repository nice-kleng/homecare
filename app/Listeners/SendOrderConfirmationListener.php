<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\NewOrderAdminNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmationListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->load(['patient', 'service']);

        // 1. Notifikasi in-app + email ke semua admin
        User::role('admin')
            ->where('status', 'active')
            ->get()
            ->each(fn($admin) => $admin->notify(new NewOrderAdminNotification($order)));

        // 2. WhatsApp ke pasien (async via queue)
        if ($order->patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $order->patient->phone,
                message: $this->buildPatientMessage($order),
                event  : 'order.received',
                refId  : $order->id,
                refType: 'order',
            );
        }

        // 3. WhatsApp ke admin utama (jika dikonfigurasi)
        $adminPhone = config('homecare.admin_whatsapp');
        if ($adminPhone) {
            SendWhatsAppJob::dispatch(
                phone  : $adminPhone,
                message: "Order baru masuk: {$order->order_number} dari {$order->patient->name}. "
                         . "Layanan: {$order->service?->name}. Jadwal: {$order->visit_date} {$order->visit_time_start}.",
                event  : 'order.created.admin',
                refId  : $order->id,
                refType: 'order',
            );
        }
    }

    private function buildPatientMessage(object $order): string
    {
        return "Halo {$order->patient->name},\n\n"
            . "Pesanan Anda *{$order->order_number}* telah kami terima.\n"
            . "Layanan : {$order->service?->name}\n"
            . "Jadwal  : {$order->visit_date} pukul {$order->visit_time_start}\n\n"
            . "Tim kami akan segera mengkonfirmasi. Terima kasih.";
    }
}
