<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'order_number'  => $this->order_number,
            'status'        => $this->status,
            'status_label'  => $this->statusLabel(),
            'source'        => $this->source,

            // Relasi
            'patient'       => new PatientResource($this->whenLoaded('patient')),
            'service'       => new ServiceResource($this->whenLoaded('service')),
            'service_package' => $this->whenLoaded('servicePackage', fn() => [
                'id'   => $this->servicePackage->id,
                'name' => $this->servicePackage->name,
            ]),

            // Jadwal kunjungan
            'visit_date'       => $this->visit_date,
            'visit_time_start' => $this->visit_time_start,
            'visit_time_end'   => $this->visit_time_end,

            // Alamat kunjungan
            'visit_address'    => $this->visit_address,
            'visit_address_notes' => $this->visit_address_notes,
            'visit_coordinates' => [
                'lat' => $this->visit_latitude,
                'lng' => $this->visit_longitude,
            ],

            // Medis
            'chief_complaint'  => $this->chief_complaint,
            'medical_notes'    => $this->medical_notes,
            'referral_document'=> $this->referral_document
                ? asset("storage/{$this->referral_document}")
                : null,

            // Kunjungan terkait
            'visits' => VisitResource::collection($this->whenLoaded('visits')),
            'latest_visit' => $this->whenLoaded('visits', function () {
                return $this->visits->isNotEmpty()
                    ? new VisitResource($this->visits->last())
                    : null;
            }),

            // Invoice
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),

            // Admin info
            'admin_notes'   => $this->when(
                $request->user()?->hasRole('admin'),
                $this->admin_notes
            ),
            'confirmed_by'  => $this->whenLoaded('confirmedBy', fn() => $this->confirmedBy?->name),
            'confirmed_at'  => $this->confirmed_at,

            // Pembatalan
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at'        => $this->cancelled_at,

            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'pending'     => 'Menunggu Konfirmasi',
            'confirmed'   => 'Dikonfirmasi',
            'assigned'    => 'Petugas Ditugaskan',
            'in_progress' => 'Sedang Berjalan',
            'completed'   => 'Selesai',
            'cancelled'   => 'Dibatalkan',
            'rescheduled' => 'Dijadwal Ulang',
            default       => ucfirst($this->status),
        };
    }
}
