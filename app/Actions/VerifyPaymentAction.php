<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;

/**
 * Verifikasi pembayaran & update status invoice.
 */
class VerifyPaymentAction
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function execute(Payment $payment, int $verifiedBy, ?string $notes = null): Payment
    {
        if ($payment->status !== 'pending') {
            throw new \Exception('Pembayaran sudah diproses sebelumnya.');
        }

        return $this->invoiceService->verifyPayment($payment, $verifiedBy, $notes);
    }
}
