<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    /**
     * Generate invoice otomatis setelah kunjungan selesai.
     */
    public function generateFromOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $service     = $order->service;
            $visit       = $order->visits()->where('status', 'completed')->latest()->first();

            // --- Hitung komponen biaya ---
            $serviceFee      = $service?->base_price ?? 0;
            $transportFee    = $service?->transport_fee ?? 0;
            $consumablesFee  = $this->estimateConsumablesFee($visit);
            $additionalFee   = 0;

            $subtotal = $serviceFee + $transportFee + $consumablesFee + $additionalFee;

            // Diskon paket (jika memakai service_package)
            $discountAmount = 0;
            if ($order->servicePackage) {
                $discountAmount = $order->servicePackage->discount_amount;
            }

            // Pajak (ambil dari settings, default 0%)
            $taxPct    = (float) setting('billing.tax_percentage', 0);
            $taxAmount = ($subtotal - $discountAmount) * ($taxPct / 100);

            $total = $subtotal - $discountAmount + $taxAmount;

            // Tanggungan asuransi
            $insuranceCoverage = $this->calculateInsuranceCoverage($order, $total);
            $patientLiability  = max(0, $total - $insuranceCoverage);

            $invoice = Invoice::create([
                'invoice_number'    => $this->generateInvoiceNumber(),
                'order_id'          => $order->id,
                'patient_id'        => $order->patient_id,
                'service_fee'       => $serviceFee,
                'transport_fee'     => $transportFee,
                'consumables_fee'   => $consumablesFee,
                'additional_fee'    => $additionalFee,
                'subtotal'          => $subtotal,
                'discount_amount'   => $discountAmount,
                'tax_percentage'    => $taxPct,
                'tax_amount'        => $taxAmount,
                'total_amount'      => $total,
                'insurance_coverage'=> $insuranceCoverage,
                'patient_liability' => $patientLiability,
                'status'            => 'sent',
                'issued_date'       => now()->toDateString(),
                'due_date'          => now()->addDays(
                    (int) setting('billing.due_days', 7)
                )->toDateString(),
                'created_by'        => Auth::id() ?? 1,
            ]);

            // Buat item baris invoice
            $this->createInvoiceItems($invoice, $service, $visit, $transportFee, $consumablesFee);

            // Notifikasi ke pasien
            $this->notificationService->notifyPatient(
                $order->patient,
                'invoice.generated',
                $invoice
            );

            activity()->performedOn($invoice)->causedBy(Auth::user())
                ->withProperties(['invoice_number' => $invoice->invoice_number, 'total' => $total])
                ->log('Invoice digenerate otomatis');

            return $invoice->load(['invoiceItems', 'patient', 'order.service']);
        });
    }

    /**
     * Verifikasi pembayaran yang diupload pasien.
     */
    public function verifyPayment(Payment $payment, int $verifiedBy, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $verifiedBy, $notes) {
            $payment->update([
                'status'             => 'verified',
                'verified_by'        => $verifiedBy,
                'verified_at'        => now(),
                'verification_notes' => $notes,
            ]);

            $invoice = $payment->invoice;
            $paidAmount = $invoice->payments()->where('status', 'verified')->sum('amount');

            if ($paidAmount >= $invoice->patient_liability) {
                $invoice->update(['status' => 'paid']);
            } elseif ($paidAmount > 0) {
                $invoice->update(['status' => 'partial']);
            }

            $this->notificationService->notifyPatient(
                $invoice->patient,
                'payment.verified',
                $invoice
            );

            activity()->performedOn($payment)->causedBy(Auth::user())
                ->withProperties(['amount' => $payment->amount])
                ->log('Pembayaran diverifikasi');

            return $payment->fresh(['invoice']);
        });
    }

    /**
     * Tolak bukti pembayaran (misal: bukti tidak valid / nominal salah).
     */
    public function rejectPayment(Payment $payment, string $reason): Payment
    {
        $payment->update([
            'status'             => 'rejected',
            'verification_notes' => $reason,
        ]);

        $this->notificationService->notifyPatient(
            $payment->invoice->patient,
            'payment.rejected',
            $payment->invoice
        );

        activity()->performedOn($payment)->causedBy(Auth::user())
            ->withProperties(['reason' => $reason])
            ->log('Pembayaran ditolak');

        return $payment->fresh();
    }

    /**
     * Rekam pembayaran baru (upload bukti oleh pasien).
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if (isset($data['proof_file']) && $data['proof_file'] instanceof \Illuminate\Http\UploadedFile) {
            $data['proof_file'] = $data['proof_file']->store('payments/proofs', 'public');
        }

        $payment = Payment::create([
            'payment_number'    => $this->generatePaymentNumber(),
            'invoice_id'        => $invoice->id,
            'patient_id'        => $invoice->patient_id,
            'amount'            => $data['amount'],
            'payment_date'      => $data['payment_date'],
            'payment_method'    => $data['payment_method'],
            'bank_name'         => $data['bank_name'] ?? null,
            'account_number'    => $data['account_number'] ?? null,
            'transfer_reference'=> $data['transfer_reference'] ?? null,
            'proof_file'        => $data['proof_file'] ?? null,
            'status'            => 'pending',
            'received_by'       => Auth::id(),
            'notes'             => $data['notes'] ?? null,
        ]);

        // Notifikasi ke admin/finance untuk verifikasi
        $this->notificationService->notifyAdmins('payment.uploaded', $invoice);

        activity()->performedOn($payment)->causedBy(Auth::user())
            ->withProperties(['amount' => $payment->amount, 'method' => $payment->payment_method])
            ->log('Bukti pembayaran diupload');

        return $payment->load(['invoice']);
    }

    /**
     * Generate PDF invoice untuk download / print.
     */
    public function generatePdf(Invoice $invoice): \Illuminate\Http\Response
    {
        $invoice->load([
            'patient.city',
            'order.service',
            'invoiceItems',
            'payments',
        ]);

        return Pdf::loadView('pdf.invoice', compact('invoice'))
                  ->setPaper('a4')
                  ->download("Invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Tandai invoice sebagai overdue (dijalankan via cron job).
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::where('status', 'sent')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd');
        $last   = Invoice::where('invoice_number', 'like', "{$prefix}%")->max('invoice_number');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function generatePaymentNumber(): string
    {
        $prefix = 'PAY-' . now()->format('Ymd');
        $last   = Payment::where('payment_number', 'like', "{$prefix}%")->max('payment_number');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function estimateConsumablesFee(?object $visit): float
    {
        if (! $visit || empty($visit->consumables_used)) {
            return 0;
        }
        // Implementasi sesuai kebutuhan: bisa dari tabel tarif BHP
        return (float) setting('billing.default_consumables_fee', 0);
    }

    private function calculateInsuranceCoverage(Order $order, float $total): float
    {
        $patient = $order->patient;

        if ($patient->insurance_type === 'bpjs') {
            return $total; // BPJS menanggung penuh (sesuaikan dengan aturan klaim)
        }

        return 0;
    }

    private function createInvoiceItems(Invoice $invoice, $service, $visit, float $transportFee, float $consumablesFee): void
    {
        if ($service) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $service->name,
                'item_type'   => 'service',
                'quantity'    => 1,
                'unit_price'  => $service->base_price,
                'total_price' => $service->base_price,
            ]);
        }

        if ($transportFee > 0) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Biaya transportasi',
                'item_type'   => 'transport',
                'quantity'    => 1,
                'unit_price'  => $transportFee,
                'total_price' => $transportFee,
            ]);
        }

        if ($consumablesFee > 0) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Bahan habis pakai (BHP)',
                'item_type'   => 'consumable',
                'quantity'    => 1,
                'unit_price'  => $consumablesFee,
                'total_price' => $consumablesFee,
            ]);
        }
    }
}
