<?php

namespace App\Listeners;

use App\Events\VisitCompleted;
use App\Models\Staff;
use App\Notifications\VisitValidationNeededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyDoctorForValidationListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(VisitCompleted $event): void
    {
        $visit = $event->visit->load(['order.patient', 'order.medicalRecord.doctor.user']);
        $order = $visit->order;

        // Cari dokter dari rekam medis aktif pasien
        $activeRecord = $order->patient->medicalRecords()
            ->where('status', 'active')
            ->with('doctor.user')
            ->latest()
            ->first();

        if ($activeRecord && $activeRecord->doctor?->user) {
            $activeRecord->doctor->user->notify(
                new VisitValidationNeededNotification($visit)
            );
            return;
        }

        // Fallback: notifikasi semua dokter aktif
        Staff::whereHas('specialization', fn($q) => $q->where('code', 'DKT'))
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->each(fn($doctor) => $doctor->user?->notify(
                new VisitValidationNeededNotification($visit)
            ));
    }
}
