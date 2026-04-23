<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Auto-cancel satu order yang melewati batas waktu konfirmasi.
 * Dipanggil dari Command AutoCancelOrders.
 */
class AutoCancelOverdueOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue('default');
    }

    public function handle(OrderService $orderService): void
    {
        $order = Order::with(['patient', 'visits'])->find($this->orderId);

        if (! $order) {
            return;
        }

        // Double-check masih bisa dibatalkan (race condition guard)
        if (! in_array($order->status, ['pending', 'confirmed'])) {
            return;
        }

        $orderService->cancel(
            order      : $order,
            reason     : 'Order dibatalkan otomatis karena melewati batas waktu konfirmasi.',
            cancelledBy: 1, // system user ID
        );

        Log::info("Auto-cancelled order {$order->order_number} (ID: {$order->id}).");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AutoCancelOverdueOrderJob gagal. OrderID: {$this->orderId}. Error: {$exception->getMessage()}");
    }
}
