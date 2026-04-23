<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Patient;
use App\Services\OrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single-responsibility action untuk membuat order baru.
 * Dipanggil dari OrderController dan API OrderController.
 */
class CreateOrderAction
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * @param  Patient       $patient  Pasien yang memesan
     * @param  array         $data     Data tervalidasi dari FormRequest
     * @return Order
     */
    public function execute(Patient $patient, array $data): Order
    {
        // Handle upload surat rujukan jika ada
        if (isset($data['referral_document']) && $data['referral_document'] instanceof UploadedFile) {
            $data['referral_document'] = Storage::disk('public')
                ->put("referrals/{$patient->id}", $data['referral_document']);
        }

        return $this->orderService->create($patient, $data);
    }
}
