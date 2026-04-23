<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Staff;
use App\Repositories\StaffRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SchedulingService
{
    public function __construct(
        protected StaffRepository $staffRepo,
    ) {}

    /**
     * Cari petugas terbaik untuk sebuah order secara otomatis.
     * Prioritas: spesialisasi cocok → beban kerja terendah → rating tertinggi.
     */
    public function suggestStaff(Order $order): Collection
    {
        $date   = Carbon::parse($order->visit_date);
        $time   = $order->visit_time_start;
        $specId = $order->service?->specialization_id;

        $candidates = $this->staffRepo->findAvailable($date, $time, $specId);

        if ($candidates->isEmpty()) {
            // Fallback: coba semua spesialisasi jika tidak ada yang cocok
            $candidates = $this->staffRepo->findAvailable($date, $time, null);
        }

        // Hitung skor prioritas tiap kandidat
        return $candidates
            ->map(function (Staff $staff) use ($date) {
                $visitsToday = $this->staffRepo->visitsCountOnDate($staff->id, $date);
                $avgRating   = $staff->visits()->whereNotNull('rating')->avg('rating') ?? 0;

                // Skor: makin sedikit kunjungan & makin tinggi rating = prioritas lebih tinggi
                $staff->priority_score = (10 - $visitsToday) + ($avgRating * 2);
                $staff->visits_today   = $visitsToday;
                $staff->avg_rating     = round($avgRating, 1);

                return $staff;
            })
            ->sortByDesc('priority_score')
            ->values();
    }

    /**
     * Hitung beban kerja semua petugas aktif untuk rentang tanggal.
     * Berguna untuk dashboard admin & tampilan kalender.
     */
    public function workloadOverview(Carbon $from, Carbon $to): array
    {
        $staff = Staff::with(['user', 'specialization'])
            ->where('status', 'active')
            ->get();

        return $staff->map(function (Staff $s) use ($from, $to) {
            $visits = $s->visits()
                ->whereHas('order', fn($q) =>
                    $q->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
                )
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->count();

            return [
                'staff_id'       => $s->id,
                'name'           => $s->user->name,
                'specialization' => $s->specialization->name,
                'total_visits'   => $visits,
                'capacity'       => $s->max_visits_per_day * $from->diffInDays($to),
                'utilization_pct'=> $s->max_visits_per_day > 0
                    ? round($visits / ($s->max_visits_per_day * max(1, $from->diffInDays($to))) * 100, 1)
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Cek apakah petugas tersedia di slot waktu tertentu.
     */
    public function isStaffAvailable(int $staffId, string $date, string $time): bool
    {
        $staff = Staff::findOrFail($staffId);

        if ($staff->status !== 'active') {
            return false;
        }

        $carbon  = Carbon::parse($date);
        $available = $this->staffRepo->findAvailable($carbon, $time);

        return $available->contains('id', $staffId);
    }

    /**
     * Redistribusi kunjungan dari petugas yang tiba-tiba cuti/sakit.
     * Mengembalikan daftar kunjungan yang perlu di-reassign manual.
     */
    public function getAffectedVisitsOnLeave(int $staffId, string $leaveDate): Collection
    {
        $staff = Staff::findOrFail($staffId);

        return $staff->visits()
            ->with(['order.patient', 'order.service'])
            ->whereHas('order', fn($q) =>
                $q->whereDate('visit_date', $leaveDate)
            )
            ->whereIn('status', ['scheduled', 'on_the_way'])
            ->get();
    }
}
