<?php

namespace App\Actions;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\SchedulingService;
use Illuminate\Validation\ValidationException;

/**
 * Validasi ketersediaan petugas lalu tugaskan ke order.
 */
class AssignStaffAction
{
    public function __construct(
        protected OrderService      $orderService,
        protected SchedulingService $schedulingService,
    ) {}

    /**
     * @param  Order  $order
     * @param  int    $staffId
     * @param  bool   $forceAssign  Abaikan validasi ketersediaan (override admin)
     * @return Order
     *
     * @throws ValidationException
     */
    public function execute(Order $order, int $staffId, bool $forceAssign = false): Order
    {
        if (!$forceAssign) {
            $isAvailable = $this->schedulingService->isStaffAvailable(
                $staffId,
                $order->visit_date,
                $order->visit_time_start
            );

            if (!$isAvailable) {
                throw ValidationException::withMessages([
                    'staff_id' => 'Petugas tidak tersedia pada jadwal yang dipilih.',
                ]);
            }
        }

        return $this->orderService->assignStaff($order, $staffId);
    }
}
