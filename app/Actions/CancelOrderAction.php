<?php

namespace App\Actions;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Batalkan order dengan validasi business rules:
 * - Pasien hanya boleh cancel order yang masih pending/confirmed.
 * - Admin bisa cancel order sampai status assigned.
 * - Order in_progress/completed tidak bisa dibatalkan.
 */
class CancelOrderAction
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function execute(Order $order, string $reason): Order
    {
        $user = Auth::user();

        $allowedStatuses = $user->hasRole('admin')
            ? ['pending', 'confirmed', 'assigned']
            : ['pending', 'confirmed'];

        if (!in_array($order->status, $allowedStatuses)) {
            throw ValidationException::withMessages([
                'status' => "Order dengan status '{$order->status}' tidak dapat dibatalkan.",
            ]);
        }

        return $this->orderService->cancel($order, $reason, $user->id);
    }
}
