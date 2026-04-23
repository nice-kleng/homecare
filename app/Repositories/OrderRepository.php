<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class OrderRepository
{
    public function __construct(protected Order $model) {}

    /**
     * Semua order dengan filter & pagination (untuk tabel admin).
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['patient', 'service', 'orderedBy', 'visits.staff'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('order_number', 'like', "%{$filters['search']}%")
                        ->orWhereHas('patient', fn($p) =>
                            $p->where('name', 'like', "%{$filters['search']}%")
                        );
                });
            })
            ->when(isset($filters['date_from']), fn($q) =>
                $q->whereDate('visit_date', '>=', $filters['date_from'])
            )
            ->when(isset($filters['date_to']), fn($q) =>
                $q->whereDate('visit_date', '<=', $filters['date_to'])
            )
            ->when(isset($filters['service_id']), fn($q) =>
                $q->where('service_id', $filters['service_id'])
            )
            ->when(isset($filters['staff_id']), function ($q) use ($filters) {
                $q->whereHas('visits', fn($v) =>
                    $v->where('staff_id', $filters['staff_id'])
                );
            })
            ->latest('visit_date')
            ->paginate($perPage);
    }

    /**
     * Order milik pasien tertentu.
     */
    public function forPatient(int $patientId, array $filters = []): LengthAwarePaginator
    {
        return $this->model
            ->with(['service', 'visits.staff'])
            ->where('patient_id', $patientId)
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->latest()
            ->paginate(10);
    }

    /**
     * Order yang perlu diproses admin (pending / perlu konfirmasi).
     */
    public function pendingConfirmation(): Collection
    {
        return $this->model
            ->with(['patient', 'service', 'orderedBy'])
            ->where('status', 'pending')
            ->oldest()
            ->get();
    }

    /**
     * Order yang sudah dikonfirmasi tapi belum ada petugas (perlu dispatch).
     */
    public function awaitingAssignment(): Collection
    {
        return $this->model
            ->with(['patient', 'service'])
            ->where('status', 'confirmed')
            ->whereDoesntHave('visits')
            ->oldest('visit_date')
            ->get();
    }

    /**
     * Order untuk tanggal kunjungan tertentu (keperluan kalender/jadwal).
     */
    public function forDate(Carbon $date): Collection
    {
        return $this->model
            ->with(['patient', 'service', 'visits.staff'])
            ->whereDate('visit_date', $date)
            ->whereIn('status', ['confirmed', 'assigned', 'in_progress'])
            ->orderBy('visit_time_start')
            ->get();
    }

    /**
     * Statistik ringkasan untuk dashboard admin.
     */
    public function dashboardStats(): array
    {
        $today = Carbon::today();

        return [
            'total_today'       => $this->model->whereDate('visit_date', $today)->count(),
            'pending'           => $this->model->where('status', 'pending')->count(),
            'in_progress'       => $this->model->where('status', 'in_progress')->count(),
            'completed_today'   => $this->model->where('status', 'completed')
                                               ->whereDate('updated_at', $today)->count(),
            'cancelled_today'   => $this->model->where('status', 'cancelled')
                                               ->whereDate('updated_at', $today)->count(),
        ];
    }

    /**
     * Temukan order dengan relasi lengkap (untuk detail page).
     */
    public function findWithRelations(int $id): Order
    {
        return $this->model
            ->with([
                'patient.city',
                'service.serviceCategory',
                'servicePackage',
                'orderedBy',
                'visits.staff.user',
                'visits.visitDocuments',
                'invoice.payments',
                'confirmedBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Generate nomor order unik.
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd');
        $last   = $this->model
            ->where('order_number', 'like', "{$prefix}%")
            ->max('order_number');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
