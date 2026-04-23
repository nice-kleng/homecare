<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Repositories\OrderRepository;
use App\Repositories\StaffRepository;
use App\Services\SchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        protected OrderRepository   $orderRepo,
        protected StaffRepository   $staffRepo,
        protected SchedulingService $schedulingService,
    ) {}

    /**
     * Halaman kalender jadwal petugas.
     */
    public function index(Request $request): Response
    {
        $date  = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $week = [
            'start' => $date->copy()->startOfWeek(),
            'end'   => $date->copy()->endOfWeek(),
        ];

        // Jadwal semua petugas untuk minggu ini
        $schedules = Staff::with(['user', 'specialization'])
            ->where('status', 'active')
            ->get()
            ->map(function ($staff) use ($week) {
                $visits = $this->staffRepo->scheduleForRange(
                    $staff->id,
                    $week['start'],
                    $week['end']
                )->map(fn($v) => [
                    'id'           => $v->id,
                    'order_id'     => $v->order_id,
                    'order_number' => $v->order->order_number,
                    'patient_name' => $v->order->patient->name,
                    'service_name' => $v->order->service?->name,
                    'visit_date'   => $v->order->visit_date,
                    'visit_time'   => $v->order->visit_time_start,
                    'status'       => $v->status,
                ]);

                return [
                    'staff_id'       => $staff->id,
                    'name'           => $staff->user->name,
                    'specialization' => $staff->specialization->name,
                    'max_visits'     => $staff->max_visits_per_day,
                    'visits'         => $visits,
                ];
            });

        // Order yang belum punya petugas (awaiting assignment)
        $unassigned = $this->orderRepo->awaitingAssignment()
            ->map(fn($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'patient_name' => $o->patient->name,
                'service_name' => $o->service?->name,
                'visit_date'   => $o->visit_date,
                'visit_time'   => $o->visit_time_start,
                'service_specialization_id' => $o->service?->specialization_id,
            ]);

        return Inertia::render('Admin/Schedule/Index', [
            'schedules'  => $schedules,
            'unassigned' => $unassigned,
            'weekDates'  => collect(range(0, 6))->map(fn($i) => [
                'date'  => $week['start']->copy()->addDays($i)->toDateString(),
                'label' => $week['start']->copy()->addDays($i)->isoFormat('ddd, D MMM'),
            ]),
            'currentDate'=> $date->toDateString(),
        ]);
    }

    /**
     * API: cari petugas tersedia untuk slot waktu tertentu (dipakai dropdown assign).
     */
    public function availableStaff(Request $request): JsonResponse
    {
        $request->validate([
            'date'              => ['required', 'date'],
            'time'              => ['required', 'date_format:H:i'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $staff = $this->staffRepo->findAvailable(
            Carbon::parse($request->date),
            $request->time,
            $request->specialization_id,
        )->map(fn($s) => [
            'id'             => $s->id,
            'name'           => $s->user->name,
            'specialization' => $s->specialization->name,
            'visits_today'   => $this->staffRepo->visitsCountOnDate(
                $s->id,
                Carbon::parse($request->date)
            ),
            'max_visits'     => $s->max_visits_per_day,
        ]);

        return response()->json(['staff' => $staff]);
    }

    /**
     * Workload overview untuk chart utilisasi petugas.
     */
    public function workload(Request $request): JsonResponse
    {
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::now()->startOfWeek();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::now()->endOfWeek();

        return response()->json([
            'workload' => $this->schedulingService->workloadOverview($from, $to),
        ]);
    }
}
