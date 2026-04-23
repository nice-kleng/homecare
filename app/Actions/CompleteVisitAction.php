<?php

namespace App\Actions;

use App\Models\Visit;
use App\Services\InvoiceService;
use App\Services\MedicalRecordService;
use App\Services\VisitService;
use Illuminate\Support\Facades\DB;

/**
 * Selesaikan kunjungan: check-out → generate invoice → update rekam medis.
 * Semua dalam satu transaksi database agar tidak ada state yang tanggung.
 */
class CompleteVisitAction
{
    public function __construct(
        protected VisitService         $visitService,
        protected InvoiceService       $invoiceService,
        protected MedicalRecordService $medicalRecordService,
    ) {}

    /**
     * @param  Visit  $visit
     * @param  array  $checkoutData  ['latitude', 'longitude', 'patient_signature', 'staff_signature']
     * @return array  ['visit' => Visit, 'invoice' => Invoice]
     */
    public function execute(Visit $visit, array $checkoutData): array
    {
        return DB::transaction(function () use ($visit, $checkoutData) {
            // 1. Check-out & selesaikan kunjungan
            $completedVisit = $this->visitService->checkOut($visit, $checkoutData);

            $order = $completedVisit->order;

            // 2. Tambahkan progress note ke rekam medis aktif pasien
            $activeEpisode = $this->medicalRecordService->getActiveEpisode($order->patient);
            if ($activeEpisode) {
                $this->medicalRecordService->addProgressNote($activeEpisode, [
                    'visit_id'        => $completedVisit->id,
                    'staff_id'        => $completedVisit->staff_id,
                    'progress_note'   => $completedVisit->soap_assessment ?? 'Kunjungan selesai.',
                    'condition_trend' => 'stable',
                ]);
            }

            // 3. Generate invoice otomatis
            $invoice = $this->invoiceService->generateFromOrder($order);

            return [
                'visit'   => $completedVisit->fresh(['order.patient', 'order.service', 'staff.user']),
                'invoice' => $invoice,
            ];
        });
    }
}
