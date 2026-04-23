<?php

namespace App\Repositories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StaffRepository
{
    public function __construct(protected Staff $model) {}

    /**
     * Cari petugas yang tersedia pada tanggal & jam tertentu,
     * dengan spesialisasi yang dibutuhkan layanan.
     */
    public function findAvailable(
        Carbon $date,
        string $timeStart,
        ?int   $specializationId = null
    ): Collection {
        $dayOfWeek = strtolower($date->englishDayOfWeek);

        return $this->model
            ->with(['user', 'specialization', 'staffSchedules'])
            ->where('status', 'active')
            ->when($specializationId, fn($q) =>
                $q->where('specialization_id', $specializationId)
            )
            // Punya jadwal kerja di hari itu
            ->whereHas('staffSchedules', fn($q) =>
                $q->where('day_of_week', $dayOfWeek)
                  ->where('is_active', true)
                  ->where('start_time', '<=', $timeStart)
                  ->where('end_time', '>', $timeStart)
            )
            // Tidak sedang cuti
            ->whereDoesntHave('staffLeaves', fn($q) =>
                $q->where('leave_date', $date->toDateString())
                  ->where('status', 'approved')
            )
            // Belum melebihi max kunjungan per hari
            ->whereRaw('(
                SELECT COUNT(*) FROM visits v
                JOIN orders o ON v.order_id = o.id
                WHERE v.staff_id = staff.id
                  AND DATE(o.visit_date) = ?
                  AND v.status NOT IN (?, ?)
            ) < staff.max_visits_per_day', [$date->toDateString(), 'cancelled', 'no_show'])
            ->get();
    }

    /**
     * Hitung jumlah kunjungan petugas pada tanggal tertentu.
     */
    public function visitsCountOnDate(int $staffId, Carbon $date): int
    {
        return $this->model->findOrFail($staffId)
            ->visits()
            ->whereHas('order', fn($q) =>
                $q->whereDate('visit_date', $date)
            )
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();
    }

    /**
     * Jadwal lengkap petugas untuk rentang tanggal (kalender admin).
     */
    public function scheduleForRange(int $staffId, Carbon $from, Carbon $to): Collection
    {
        return $this->model->findOrFail($staffId)
            ->visits()
            ->with(['order.patient', 'order.service'])
            ->whereHas('order', fn($q) =>
                $q->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
                  ->whereNotIn('status', ['cancelled'])
            )
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Semua petugas aktif beserta statistik bulan ini (untuk tabel petugas).
     */
    public function allWithMonthlyStats(): Collection
    {
        return $this->model
            ->with(['user', 'specialization'])
            ->where('status', 'active')
            ->withCount([
                'visits as visits_this_month' => fn($q) =>
                    $q->whereMonth('scheduled_at', now()->month)
                      ->where('status', 'completed'),
            ])
            ->get();
    }

    /**
     * Generate employee ID unik.
     */
    public function generateEmployeeId(string $specializationCode): string
    {
        $prefix = strtoupper($specializationCode) . now()->format('Y');
        $last   = $this->model
            ->where('employee_id', 'like', "{$prefix}%")
            ->max('employee_id');

        $seq = $last ? (int) substr($last, -3) + 1 : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
