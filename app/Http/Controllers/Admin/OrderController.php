<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AssignStaffAction;
use App\Actions\CancelOrderAction;
use App\Actions\GenerateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Repositories\OrderRepository;
use App\Repositories\PatientRepository;
use App\Repositories\StaffRepository;
use App\Services\OrderService;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderRepository   $orderRepo,
        protected StaffRepository   $staffRepo,
        protected PatientRepository $patientRepo,
        protected OrderService      $orderService,
        protected SchedulingService $schedulingService,
        protected AssignStaffAction $assignStaffAction,
        protected CancelOrderAction $cancelOrderAction,
        protected GenerateInvoiceAction $generateInvoiceAction,
    ) {}

    /**
     * Daftar semua order dengan filter & pagination.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search', 'date_from', 'date_to', 'service_id', 'staff_id']);

        $orders = $this->orderRepo->paginate($filters, 20);

        return Inertia::render('Admin/Orders/Index', [
            'orders'  => OrderResource::collection($orders),
            'filters' => $filters,
            'stats'   => $this->orderRepo->dashboardStats(),
            'services'=> Service::active()->select('id', 'name')->get(),
        ]);
    }

    /**
     * Detail order + sugesti petugas untuk di-assign.
     */
    public function show(Order $order): Response
    {
        $order = $this->orderRepo->findWithRelations($order->id);

        $suggestedStaff = [];
        if (in_array($order->status, ['confirmed', 'pending'])) {
            $suggestedStaff = $this->schedulingService
                ->suggestStaff($order)
                ->map(fn($s) => [
                    'id'             => $s->id,
                    'name'           => $s->user->name,
                    'specialization' => $s->specialization->name,
                    'visits_today'   => $s->visits_today,
                    'avg_rating'     => $s->avg_rating,
                    'priority_score' => round($s->priority_score, 1),
                    'str_valid'      => $s->str_expired_at
                        ? \Carbon\Carbon::parse($s->str_expired_at)->isFuture()
                        : null,
                ]);
        }

        return Inertia::render('Admin/Orders/Show', [
            'order'          => new OrderResource($order),
            'suggestedStaff' => $suggestedStaff,
        ]);
    }

    /**
     * Konfirmasi order + opsional assign petugas sekaligus.
     */
    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'staff_id'   => ['nullable', 'exists:staff,id'],
            'admin_notes'=> ['nullable', 'string', 'max:500'],
        ]);

        if ($order->status !== 'pending') {
            return back()->with('error', 'Hanya order berstatus pending yang bisa dikonfirmasi.');
        }

        if ($request->admin_notes) {
            $order->update(['admin_notes' => $request->admin_notes]);
        }

        $this->orderService->confirm($order, auth()->id(), $request->staff_id);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order berhasil dikonfirmasi.');
    }

    /**
     * Tugaskan petugas ke order yang sudah dikonfirmasi.
     */
    public function assign(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'staff_id'    => ['required', 'exists:staff,id'],
            'force_assign'=> ['boolean'],
        ]);

        $this->assignStaffAction->execute(
            order      : $order,
            staffId    : $request->staff_id,
            forceAssign: (bool) $request->force_assign,
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Petugas berhasil ditugaskan.');
    }

    /**
     * Batalkan order.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $this->cancelOrderAction->execute($order, $request->reason);

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Order berhasil dibatalkan.');
    }

    /**
     * Jadwal ulang order.
     */
    public function reschedule(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'visit_date'       => ['required', 'date', 'after_or_equal:today'],
            'visit_time_start' => ['required', 'date_format:H:i'],
            'visit_time_end'   => ['nullable', 'date_format:H:i', 'after:visit_time_start'],
        ]);

        $this->orderService->reschedule($order, $request->only([
            'visit_date', 'visit_time_start', 'visit_time_end',
        ]));

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Order berhasil dijadwal ulang.');
    }

    /**
     * Generate invoice manual untuk order yang sudah selesai.
     */
    public function generateInvoice(Order $order): RedirectResponse
    {
        $invoice = $this->generateInvoiceAction->execute($order);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} berhasil digenerate.");
    }
}
