<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use App\Repositories\OrderRepository;
use App\Repositories\ReportRepository;
use App\Services\SchedulingService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderRepository   $orderRepo,
        protected ReportRepository  $reportRepo,
        protected SchedulingService $schedulingService,
    ) {}

    public function index(): Response
    {
        $today     = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();
        $monthStart= Carbon::now()->startOfMonth();
        $monthEnd  = Carbon::now()->endOfMonth();

        $stats = [
            'today' => [
                'total_orders'    => Order::whereDate('visit_date', $today)->count(),
                'pending'         => Order::where('status', 'pending')->count(),
                'in_progress'     => Order::where('status', 'in_progress')->count(),
                'completed_today' => Order::where('status', 'completed')
                                         ->whereDate('updated_at', $today)->count(),
            ],
            'month' => $this->reportRepo->summary($monthStart, $monthEnd),
        ];

        $pendingOrders = Order::with(['patient', 'service'])
            ->where('status', 'pending')
            ->oldest()
            ->take(10)
            ->get()
            ->map(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'patient_name' => $o->patient->name,
                'service_name' => $o->service?->name,
                'visit_date'   => $o->visit_date,
                'visit_time'   => $o->visit_time_start,
                'created_at'   => $o->created_at->diffForHumans(),
            ]);

        $todaySchedule = $this->orderRepo->forDate($today)
            ->map(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'patient_name' => $o->patient->name,
                'service_name' => $o->service?->name,
                'visit_time'   => $o->visit_time_start,
                'status'       => $o->status,
                'staff_name'   => $o->visits->last()?->staff?->user?->name,
            ]);

        $activeStaff = Staff::with(['user', 'specialization'])
            ->where('status', 'active')
            ->withCount([
                'visits as visits_today' => fn($q) =>
                    $q->whereHas('order', fn($o) => $o->whereDate('visit_date', $today))
                      ->whereNotIn('status', ['cancelled', 'no_show']),
            ])
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->user->name,
                'specialization' => $s->specialization->name,
                'visits_today'   => $s->visits_today,
                'max_visits'     => $s->max_visits_per_day,
                'status'         => $s->status,
            ]);

        $revenueChart = $this->reportRepo
            ->revenueByPeriod(Carbon::now()->subDays(6), $today, 'day')
            ->map(fn($r) => [
                'period'        => $r->period,
                'total_revenue' => (float) $r->total_revenue,
                'invoice_count' => (int) $r->invoice_count,
            ]);

        $workload = $this->schedulingService->workloadOverview($weekStart, $weekEnd);

        return Inertia::render('Admin/Dashboard', [
            'stats'         => $stats,
            'pendingOrders' => $pendingOrders,
            'todaySchedule' => $todaySchedule,
            'activeStaff'   => $activeStaff,
            'revenueChart'  => $revenueChart,
            'workload'      => $workload,
        ]);
    }
}
