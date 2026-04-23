<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function __construct(
        protected VisitService $visitService,
    ) {}

    public function index(Request $request): Response
    {
        $visits = Visit::with(['order.patient', 'order.service', 'staff.user'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) =>
                $q->whereHas('order', fn($o) => $o->whereDate('visit_date', $request->date))
            )
            ->when($request->staff_id, fn($q) => $q->where('staff_id', $request->staff_id))
            ->when($request->needs_validation, fn($q) =>
                $q->where('status', 'completed')->where('is_validated', false)
            )
            ->latest('scheduled_at')
            ->paginate(20);

        return Inertia::render('Admin/Visits/Index', [
            'visits'  => VisitResource::collection($visits),
            'filters' => $request->only(['status', 'date', 'staff_id', 'needs_validation']),
            'stats'   => [
                'needs_validation' => Visit::where('status', 'completed')
                                          ->where('is_validated', false)->count(),
                'no_show_today'    => Visit::where('status', 'no_show')
                                          ->whereDate('updated_at', today())->count(),
            ],
        ]);
    }

    public function show(Visit $visit): Response
    {
        $visit->load([
            'order.patient',
            'order.service',
            'staff.user',
            'staff.specialization',
            'visitDocuments',
            'validatedBy',
        ]);

        return Inertia::render('Admin/Visits/Show', [
            'visit' => new VisitResource($visit),
        ]);
    }

    /**
     * Admin bisa force-validate kunjungan (jika dokter tidak merespons).
     */
    public function validate(Request $request, Visit $visit): RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->visitService->validate($visit, auth()->id(), $request->notes);

        return back()->with('success', 'Laporan kunjungan berhasil divalidasi.');
    }
}
