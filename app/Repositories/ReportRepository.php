<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /**
     * Statistik pendapatan per periode (untuk laporan keuangan & chart).
     */
    public function revenueByPeriod(Carbon $from, Carbon $to, string $groupBy = 'day'): Collection
    {
        $format = match ($groupBy) {
            'month' => '%Y-%m',
            'week'  => '%Y-%u',
            default => '%Y-%m-%d',
        };

        return Invoice::query()
            ->select(
                DB::raw("DATE_FORMAT(issued_date, '{$format}') as period"),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(insurance_coverage) as insurance_coverage'),
                DB::raw('SUM(patient_liability) as patient_liability'),
                DB::raw('COUNT(*) as invoice_count'),
            )
            ->where('status', 'paid')
            ->whereBetween('issued_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Ringkasan kunjungan per layanan (untuk laporan operasional).
     */
    public function visitsByService(Carbon $from, Carbon $to): Collection
    {
        return Visit::query()
            ->select(
                'services.name as service_name',
                DB::raw('COUNT(visits.id) as total_visits'),
                DB::raw('COUNT(CASE WHEN visits.status = "completed" THEN 1 END) as completed'),
                DB::raw('COUNT(CASE WHEN visits.status = "no_show" THEN 1 END) as no_show'),
                DB::raw('AVG(visits.rating) as avg_rating'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, visits.arrived_at, visits.completed_at)) as avg_duration_minutes'),
            )
            ->join('orders', 'visits.order_id', '=', 'orders.id')
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->whereBetween('orders.visit_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total_visits')
            ->get();
    }

    /**
     * Performa petugas dalam periode (untuk evaluasi SDM).
     */
    public function staffPerformance(Carbon $from, Carbon $to): Collection
    {
        return Visit::query()
            ->select(
                'users.name as staff_name',
                'specializations.name as specialization',
                DB::raw('COUNT(visits.id) as total_visits'),
                DB::raw('COUNT(CASE WHEN visits.status = "completed" THEN 1 END) as completed'),
                DB::raw('COUNT(CASE WHEN visits.status = "no_show" THEN 1 END) as no_show'),
                DB::raw('AVG(visits.rating) as avg_rating'),
                DB::raw('SUM(CASE WHEN visits.is_validated = 1 THEN 1 ELSE 0 END) as validated'),
            )
            ->join('staff', 'visits.staff_id', '=', 'staff.id')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->join('specializations', 'staff.specialization_id', '=', 'specializations.id')
            ->join('orders', 'visits.order_id', '=', 'orders.id')
            ->whereBetween('orders.visit_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('staff.id', 'users.name', 'specializations.name')
            ->orderByDesc('completed')
            ->get();
    }

    /**
     * Status piutang (invoice belum lunas).
     */
    public function outstandingInvoices(): Collection
    {
        return Invoice::query()
            ->with(['patient', 'order.service'])
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Statistik ringkasan untuk periode tertentu (dipakai export & dashboard).
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        return [
            'total_orders'       => Order::whereBetween('visit_date', [$from, $to])->count(),
            'completed_visits'   => Visit::where('status', 'completed')
                                         ->whereHas('order', fn($q) =>
                                             $q->whereBetween('visit_date', [$from, $to])
                                         )->count(),
            'total_revenue'      => Invoice::where('status', 'paid')
                                           ->whereBetween('issued_date', [$from, $to])
                                           ->sum('total_amount'),
            'avg_rating'         => Visit::whereBetween('scheduled_at', [$from, $to])
                                         ->whereNotNull('rating')
                                         ->avg('rating'),
            'cancellation_rate'  => Order::whereBetween('visit_date', [$from, $to])->count() > 0
                ? round(
                    Order::where('status', 'cancelled')
                         ->whereBetween('visit_date', [$from, $to])->count()
                    / Order::whereBetween('visit_date', [$from, $to])->count() * 100,
                    1
                  )
                : 0,
        ];
    }
}
