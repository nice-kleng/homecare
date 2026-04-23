<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Service;
use App\Repositories\OrderRepository;
use App\Repositories\StaffRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected OrderRepository  $orderRepo,
        protected StaffRepository  $staffRepo,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Buat order baru dari form pasien.
     * Seluruh proses dibungkus transaction agar atomic.
     */
    public function create(Patient $patient, array $data): Order
    {
        return DB::transaction(function () use ($patient, $data) {
            $order = Order::create([
                'order_number'      => $this->orderRepo->generateOrderNumber(),
                'patient_id'        => $patient->id,
                'service_id'        => $data['service_id'] ?? null,
                'service_package_id'=> $data['service_package_id'] ?? null,
                'ordered_by'        => Auth::id(),
                'visit_date'        => $data['visit_date'],
                'visit_time_start'  => $data['visit_time_start'],
                'visit_time_end'    => $data['visit_time_end'] ?? null,
                'visit_address'     => $data['visit_address'],
                'visit_rt'          => $data['visit_rt'] ?? null,
                'visit_rw'          => $data['visit_rw'] ?? null,
                'visit_village_id'  => $data['visit_village_id'] ?? null,
                'visit_district_id' => $data['visit_district_id'] ?? null,
                'visit_city_id'     => $data['visit_city_id'] ?? null,
                'visit_postal_code' => $data['visit_postal_code'] ?? null,
                'visit_latitude'    => $data['visit_latitude'] ?? null,
                'visit_longitude'   => $data['visit_longitude'] ?? null,
                'visit_address_notes'=> $data['visit_address_notes'] ?? null,
                'chief_complaint'   => $data['chief_complaint'] ?? null,
                'medical_notes'     => $data['medical_notes'] ?? null,
                'referral_document' => $data['referral_document'] ?? null,
                'source'            => $data['source'] ?? 'web',
                'status'            => 'pending',
            ]);

            // Notifikasi ke admin: ada order masuk
            $this->notificationService->notifyAdmins('order.created', $order);

            // Notifikasi ke pasien: order diterima
            $this->notificationService->notifyPatient($patient, 'order.received', $order);

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->withProperties(['order_number' => $order->order_number])
                ->log('Order baru dibuat');

            return $order->load(['patient', 'service']);
        });
    }

    /**
     * Admin konfirmasi order & opsional langsung assign petugas.
     */
    public function confirm(Order $order, int $adminId, ?int $staffId = null): Order
    {
        return DB::transaction(function () use ($order, $adminId, $staffId) {
            $order->update([
                'status'        => $staffId ? 'assigned' : 'confirmed',
                'confirmed_by'  => $adminId,
                'confirmed_at'  => now(),
            ]);

            if ($staffId) {
                $this->assignStaff($order, $staffId);
            }

            $this->notificationService->notifyPatient(
                $order->patient,
                'order.confirmed',
                $order
            );

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->log('Order dikonfirmasi');

            return $order->fresh(['patient', 'service', 'visits.staff']);
        });
    }

    /**
     * Tugaskan petugas ke order (buat visit record).
     */
    public function assignStaff(Order $order, int $staffId): Order
    {
        return DB::transaction(function () use ($order, $staffId) {
            // Buat record visit
            $order->visits()->create([
                'staff_id'     => $staffId,
                'scheduled_at' => $order->visit_date . ' ' . $order->visit_time_start,
                'status'       => 'scheduled',
            ]);

            $order->update(['status' => 'assigned']);

            // Notifikasi ke petugas
            $staff = \App\Models\Staff::with('user')->findOrFail($staffId);
            $this->notificationService->notifyStaff($staff, 'visit.assigned', $order);

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->withProperties(['staff_id' => $staffId])
                ->log('Petugas ditugaskan');

            return $order->fresh(['visits.staff.user']);
        });
    }

    /**
     * Batalkan order beserta alasannya.
     */
    public function cancel(Order $order, string $reason, int $cancelledBy): Order
    {
        return DB::transaction(function () use ($order, $reason, $cancelledBy) {
            $order->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_by'        => $cancelledBy,
                'cancelled_at'        => now(),
            ]);

            // Batalkan visit yang terkait jika ada
            $order->visits()
                  ->whereIn('status', ['scheduled', 'on_the_way'])
                  ->update(['status' => 'cancelled']);

            $this->notificationService->notifyPatient(
                $order->patient,
                'order.cancelled',
                $order
            );

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->withProperties(['reason' => $reason])
                ->log('Order dibatalkan');

            return $order->fresh();
        });
    }

    /**
     * Jadwal ulang order ke tanggal & jam baru.
     */
    public function reschedule(Order $order, array $newSchedule): Order
    {
        return DB::transaction(function () use ($order, $newSchedule) {
            // Clone order lama sebagai referensi
            $oldOrder = $order->replicate();

            $order->update([
                'visit_date'       => $newSchedule['visit_date'],
                'visit_time_start' => $newSchedule['visit_time_start'],
                'visit_time_end'   => $newSchedule['visit_time_end'] ?? null,
                'status'           => 'confirmed',
            ]);

            // Reset visit lama, buat ulang setelah petugas di-assign ulang
            $order->visits()->whereIn('status', ['scheduled'])->update(['status' => 'cancelled']);

            $this->notificationService->notifyPatient(
                $order->patient,
                'order.rescheduled',
                $order
            );

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old_date' => $oldOrder->visit_date,
                    'new_date' => $newSchedule['visit_date'],
                ])
                ->log('Order dijadwal ulang');

            return $order->fresh();
        });
    }

    /**
     * Daftar slot waktu tersedia untuk layanan pada tanggal tertentu.
     */
    public function getAvailableSlots(Service $service, string $date): array
    {
        $availableStaff = $this->staffRepo->findAvailable(
            \Carbon\Carbon::parse($date),
            '00:00',
            $service->specialization_id
        );

        if ($availableStaff->isEmpty()) {
            return [];
        }

        $slots = [];
        $times = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];

        foreach ($times as $time) {
            $staffForSlot = $this->staffRepo->findAvailable(
                \Carbon\Carbon::parse($date),
                $time,
                $service->specialization_id
            );

            if ($staffForSlot->isNotEmpty()) {
                $slots[] = [
                    'time'          => $time,
                    'available'     => true,
                    'staff_count'   => $staffForSlot->count(),
                ];
            }
        }

        return $slots;
    }
}
