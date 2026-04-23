<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Specialization;
use App\Models\Staff;
use App\Models\StaffLeave;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Repositories\StaffRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(
        protected StaffRepository $staffRepo,
    ) {}

    public function index(): Response
    {
        $staff = $this->staffRepo->allWithMonthlyStats();

        return Inertia::render('Admin/Staff/Index', [
            'staff'           => StaffResource::collection($staff),
            'specializations' => Specialization::select('id', 'name', 'code')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Staff/Create', [
            'specializations' => Specialization::select('id', 'name', 'code')->get(),
            'days'            => ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', 'unique:users,email'],
            'phone'               => ['required', 'string', 'max:20'],
            'password'            => ['required', 'string', 'min:8'],
            'specialization_id'   => ['required', 'exists:specializations,id'],
            'nik'                 => ['nullable', 'string', 'size:16', 'unique:staff,nik'],
            'gender'              => ['required', 'in:laki-laki,perempuan'],
            'birth_date'          => ['nullable', 'date'],
            'address'             => ['nullable', 'string'],
            'city_id'             => ['nullable', 'exists:cities,id'],
            'str_number'          => ['nullable', 'string', 'max:50'],
            'str_expired_at'      => ['nullable', 'date'],
            'sip_number'          => ['nullable', 'string', 'max:50'],
            'sip_expired_at'      => ['nullable', 'date'],
            'max_visits_per_day'  => ['required', 'integer', 'min:1', 'max:20'],
            'service_radius_km'   => ['required', 'numeric', 'min:1'],
            'schedules'           => ['nullable', 'array'],
            'schedules.*.day'     => ['required_with:schedules', 'string'],
            'schedules.*.start'   => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end'     => ['required_with:schedules', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($validated) {
            // Buat user account
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'status'   => 'active',
            ]);
            $user->assignRole('petugas');

            // Buat data staff
            $spec = Specialization::findOrFail($validated['specialization_id']);

            $staff = Staff::create([
                'user_id'            => $user->id,
                'specialization_id'  => $validated['specialization_id'],
                'employee_id'        => $this->staffRepo->generateEmployeeId($spec->code),
                'nik'                => $validated['nik'] ?? null,
                'gender'             => $validated['gender'],
                'birth_date'         => $validated['birth_date'] ?? null,
                'address'            => $validated['address'] ?? null,
                'city_id'            => $validated['city_id'] ?? null,
                'phone'              => $validated['phone'],
                'str_number'         => $validated['str_number'] ?? null,
                'str_expired_at'     => $validated['str_expired_at'] ?? null,
                'sip_number'         => $validated['sip_number'] ?? null,
                'sip_expired_at'     => $validated['sip_expired_at'] ?? null,
                'max_visits_per_day' => $validated['max_visits_per_day'],
                'service_radius_km'  => $validated['service_radius_km'],
                'status'             => 'active',
            ]);

            // Simpan jadwal kerja
            if (!empty($validated['schedules'])) {
                foreach ($validated['schedules'] as $sched) {
                    StaffSchedule::create([
                        'staff_id'    => $staff->id,
                        'day_of_week' => $sched['day'],
                        'start_time'  => $sched['start'],
                        'end_time'    => $sched['end'],
                        'is_active'   => true,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Petugas berhasil ditambahkan.');
    }

    public function show(Staff $staff): Response
    {
        $staff->load([
            'user', 'specialization', 'staffSchedules', 'city',
        ]);

        $recentVisits = $staff->visits()
            ->with(['order.patient', 'order.service'])
            ->where('status', 'completed')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($v) => [
                'id'           => $v->id,
                'patient_name' => $v->order->patient->name,
                'service_name' => $v->order->service?->name,
                'completed_at' => $v->completed_at,
                'rating'       => $v->rating,
            ]);

        return Inertia::render('Admin/Staff/Show', [
            'staff'        => new StaffResource($staff),
            'recentVisits' => $recentVisits,
            'stats'        => [
                'total_visits'   => $staff->visits()->where('status', 'completed')->count(),
                'avg_rating'     => round($staff->visits()->whereNotNull('rating')->avg('rating') ?? 0, 1),
                'visits_month'   => $staff->visits()
                                         ->whereMonth('scheduled_at', now()->month)
                                         ->where('status', 'completed')
                                         ->count(),
            ],
        ]);
    }

    public function edit(Staff $staff): Response
    {
        $staff->load(['user', 'specialization', 'staffSchedules']);

        return Inertia::render('Admin/Staff/Edit', [
            'staff'           => new StaffResource($staff),
            'specializations' => Specialization::select('id', 'name', 'code')->get(),
            'days'            => ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'],
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'phone'              => ['required', 'string', 'max:20'],
            'specialization_id'  => ['required', 'exists:specializations,id'],
            'str_number'         => ['nullable', 'string', 'max:50'],
            'str_expired_at'     => ['nullable', 'date'],
            'sip_number'         => ['nullable', 'string', 'max:50'],
            'sip_expired_at'     => ['nullable', 'date'],
            'max_visits_per_day' => ['required', 'integer', 'min:1', 'max:20'],
            'service_radius_km'  => ['required', 'numeric', 'min:1'],
            'status'             => ['required', 'in:active,inactive,cuti,off_duty'],
            'schedules'          => ['nullable', 'array'],
            'schedules.*.day'    => ['required_with:schedules', 'string'],
            'schedules.*.start'  => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end'    => ['required_with:schedules', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($validated, $staff) {
            $staff->user->update([
                'name'  => $validated['name'],
                'phone' => $validated['phone'],
            ]);

            $staff->update([
                'specialization_id'  => $validated['specialization_id'],
                'phone'              => $validated['phone'],
                'str_number'         => $validated['str_number'] ?? null,
                'str_expired_at'     => $validated['str_expired_at'] ?? null,
                'sip_number'         => $validated['sip_number'] ?? null,
                'sip_expired_at'     => $validated['sip_expired_at'] ?? null,
                'max_visits_per_day' => $validated['max_visits_per_day'],
                'service_radius_km'  => $validated['service_radius_km'],
                'status'             => $validated['status'],
            ]);

            // Sinkronisasi jadwal kerja
            if (isset($validated['schedules'])) {
                $staff->staffSchedules()->delete();
                foreach ($validated['schedules'] as $sched) {
                    StaffSchedule::create([
                        'staff_id'    => $staff->id,
                        'day_of_week' => $sched['day'],
                        'start_time'  => $sched['start'],
                        'end_time'    => $sched['end'],
                        'is_active'   => true,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('success', 'Data petugas berhasil diperbarui.');
    }

    /**
     * Approve / reject pengajuan cuti petugas.
     */
    public function approveLeave(Request $request, StaffLeave $leave): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'in:approved,rejected'],
        ]);

        $leave->update([
            'status'      => $request->action,
            'approved_by' => auth()->id(),
        ]);

        $label = $request->action === 'approved' ? 'disetujui' : 'ditolak';

        return back()->with('success', "Pengajuan cuti berhasil {$label}.");
    }
}
