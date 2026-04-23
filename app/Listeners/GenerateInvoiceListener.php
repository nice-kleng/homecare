<?php

namespace App\Listeners;

use App\Events\VisitCompleted;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\VisitCompletedNotification;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceListener implements ShouldQueue
{
    public string $queue   = 'default';
    public int    $tries   = 3;
    public int    $backoff = 10;

    public function __construct(protected InvoiceService $invoiceService) {}

    public function handle(VisitCompleted $event): void
    {
        $visit = $event->visit->load(['order.patient.user', 'order.service', 'order.invoice']);
        $order = $visit->order;

        // Jangan generate ulang jika sudah ada invoice aktif
        if ($order->invoice()->whereNotIn('status', ['cancelled'])->exists()) {
            Log::info("Invoice sudah ada untuk order {$order->order_number}, skip generate.");
            return;
        }

        // Generate invoice
        $invoice = $this->invoiceService->generateFromOrder($order);

        Log::info("Invoice {$invoice->invoice_number} berhasil digenerate untuk order {$order->order_number}.");

        // Notifikasi ke pasien: kunjungan selesai + minta rating
        if ($order->patient->user) {
            $order->patient->user->notify(new VisitCompletedNotification($order));
        }

        // WhatsApp ke pasien
        if ($order->patient->phone) {
            SendWhatsAppJob::dispatch(
                phone  : $order->patient->phone,
                message: "Halo {$order->patient->name},\n\n"
                         . "Kunjungan telah selesai. Invoice *{$invoice->invoice_number}* diterbitkan.\n"
                         . "Total Tagihan : Rp " . number_format($invoice->patient_liability, 0, ',', '.') . "\n"
                         . "Jatuh Tempo   : {$invoice->due_date}\n\n"
                         . "Silakan lakukan pembayaran sebelum jatuh tempo. Terima kasih.",
                event  : 'invoice.generated',
                refId  : $invoice->id,
                refType: 'invoice',
            );
        }
    }

    public function failed(VisitCompleted $event, \Throwable $exception): void
    {
        Log::error("GenerateInvoiceListener gagal untuk visit {$event->visit->id}: {$exception->getMessage()}");
    }
}
