<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VerifyPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ReportRepository;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService    $invoiceService,
        protected VerifyPaymentAction $verifyPaymentAction,
        protected ReportRepository  $reportRepo,
    ) {}

    public function index(Request $request): Response
    {
        $invoices = Invoice::with(['patient', 'order.service'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) =>
                $q->where('invoice_number', 'like', "%{$request->search}%")
                  ->orWhereHas('patient', fn($p) =>
                      $p->where('name', 'like', "%{$request->search}%")
                  )
            )
            ->when($request->date_from, fn($q) =>
                $q->whereDate('issued_date', '>=', $request->date_from)
            )
            ->when($request->date_to, fn($q) =>
                $q->whereDate('issued_date', '<=', $request->date_to)
            )
            ->latest('issued_date')
            ->paginate(20);

        // Pembayaran pending verifikasi
        $pendingPayments = Payment::with(['invoice.patient'])
            ->where('status', 'pending')
            ->oldest()
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'payment_number' => $p->payment_number,
                'invoice_number' => $p->invoice->invoice_number,
                'patient_name'   => $p->invoice->patient->name,
                'amount'         => $p->amount,
                'method'         => $p->payment_method,
                'uploaded_at'    => $p->created_at->diffForHumans(),
            ]);

        $outstanding = $this->reportRepo->outstandingInvoices();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices'        => InvoiceResource::collection($invoices),
            'pendingPayments' => $pendingPayments,
            'outstanding'     => InvoiceResource::collection($outstanding),
            'filters'         => $request->only(['status', 'search', 'date_from', 'date_to']),
            'summary'         => [
                'total_unpaid'  => Invoice::whereIn('status', ['sent', 'partial'])->sum('patient_liability'),
                'total_overdue' => Invoice::where('status', 'overdue')->sum('patient_liability'),
                'paid_month'    => Invoice::where('status', 'paid')
                                         ->whereMonth('updated_at', now()->month)
                                         ->sum('total_amount'),
            ],
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'patient.city',
            'order.service',
            'invoiceItems',
            'payments.verifiedBy',
        ]);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Halaman detail payment + form verifikasi.
     */
    public function showPayment(Payment $payment): Response
    {
        $payment->load(['invoice.patient', 'invoice.order.service', 'verifiedBy']);

        return Inertia::render('Admin/Invoices/PaymentVerify', [
            'payment' => new PaymentResource($payment),
            'invoice' => new InvoiceResource($payment->invoice),
        ]);
    }

    /**
     * Verifikasi bukti pembayaran.
     */
    public function verifyPayment(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->verifyPaymentAction->execute(
            payment    : $payment,
            verifiedBy : auth()->id(),
            notes      : $request->notes,
        );

        return redirect()
            ->route('admin.invoices.show', $payment->invoice_id)
            ->with('success', 'Pembayaran berhasil diverifikasi.');
    }

    /**
     * Tolak bukti pembayaran.
     */
    public function rejectPayment(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $this->invoiceService->rejectPayment($payment, $request->reason);

        return redirect()
            ->route('admin.invoices.show', $payment->invoice_id)
            ->with('success', 'Pembayaran ditolak. Pasien akan mendapatkan notifikasi.');
    }

    /**
     * Download invoice sebagai PDF.
     */
    public function downloadPdf(Invoice $invoice): HttpResponse
    {
        return $this->invoiceService->generatePdf($invoice);
    }
}
