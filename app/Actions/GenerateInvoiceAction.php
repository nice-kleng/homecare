<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;

/**
 * Generate invoice manual dari order (dipanggil admin jika auto-generate gagal).
 */
class GenerateInvoiceAction
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function execute(Order $order): Invoice
    {
        // Cek order belum punya invoice aktif
        $existing = $order->invoice()->whereNotIn('status', ['cancelled'])->first();

        if ($existing) {
            throw new \Exception("Order {$order->order_number} sudah memiliki invoice #{$existing->invoice_number}.");
        }

        if ($order->status !== 'completed') {
            throw new \Exception('Invoice hanya bisa dibuat untuk order yang sudah selesai.');
        }

        return $this->invoiceService->generateFromOrder($order);
    }
}
