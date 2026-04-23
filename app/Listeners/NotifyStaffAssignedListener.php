<?php

namespace App\Listeners;

use App\Events\StaffAssigned;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\VisitScheduledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStaffAssignedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(StaffAssigned $event): void
    {
        $order = $event->order->load(['patient', 'service', 'visits.staff.user']);
        $staff = $event->staff->load('user');

        // In-app + email ke petugas
        if ($staff->user) {
            $staff->user->notify(new VisitScheduledNotification($order));
        }

        // In-app + email ke pasien (info petugas sudah ditugaskan)
        if ($order->patient->user) {
            $order->patient->user->notify(new VisitScheduledNotification($order));
        }

        // WhatsApp ke petugas
        if ($staff->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $staff->phone,
                message: "Halo {$staff->user->name},\n\n"
                         . "Anda ditugaskan untuk kunjungan berikut:\n"
                         . "Pasien  : {$order->patient->name}\n"
                         . "Layanan : {$order->service?->name}\n"
                         . "Jadwal  : {$order->visit_date} pukul {$order->visit_time_start}\n"
                         . "Alamat  : {$order->visit_address}\n\n"
                         . "Harap konfirmasi kehadiran Anda. Terima kasih.",
                event  : 'visit.assigned',
                refId  : $order->id,
                refType: 'order',
            );
        }

        // WhatsApp ke pasien
        if ($order->patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $order->patient->phone,
                message: "Halo {$order->patient->name},\n\n"
                         . "Petugas telah ditugaskan untuk kunjungan Anda.\n"
                         . "Petugas : {$staff->user->name}\n"
                         . "Jadwal  : {$order->visit_date} pukul {$order->visit_time_start}\n"
                         . "Mohon pastikan ada di rumah. Terima kasih.",
                event  : 'visit.assigned.patient',
                refId  : $order->id,
                refType: 'order',
            );
        }
    }
}
