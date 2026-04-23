<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),

            // Waktu tracking
            'scheduled_at'  => $this->scheduled_at,
            'departed_at'   => $this->departed_at,
            'arrived_at'    => $this->arrived_at,
            'started_at'    => $this->started_at,
            'completed_at'  => $this->completed_at,
            'duration_minutes' => $this->arrived_at && $this->completed_at
                ? $this->arrived_at->diffInMinutes($this->completed_at)
                : null,

            // Lokasi
            'checkin_coordinates' => [
                'lat' => $this->checkin_latitude,
                'lng' => $this->checkin_longitude,
            ],

            // SOAP
            'soap' => [
                'subjective' => $this->soap_subjective,
                'objective'  => $this->soap_objective,
                'assessment' => $this->soap_assessment,
                'plan'       => $this->soap_plan,
            ],

            // Vital signs
            'vital_signs' => [
                'temperature'       => $this->vital_temperature,
                'pulse'             => $this->vital_pulse,
                'respiration'       => $this->vital_respiration,
                'blood_pressure'    => $this->vital_blood_pressure,
                'oxygen_saturation' => $this->vital_oxygen_saturation,
                'weight'            => $this->vital_weight,
                'blood_sugar'       => $this->vital_blood_sugar,
                'notes'             => $this->vital_notes,
            ],

            // Tindakan
            'actions_performed'         => $this->actions_performed,
            'medications_given'         => $this->medications_given,
            'consumables_used'          => $this->consumables_used,
            'next_visit_recommendation' => $this->next_visit_recommendation,
            'notes'                     => $this->notes,

            // Validasi dokter
            'is_validated'   => $this->is_validated,
            'validated_by'   => $this->whenLoaded('validatedBy', fn() => $this->validatedBy?->name),
            'validated_at'   => $this->validated_at,
            'validation_notes' => $this->validation_notes,

            // Rating
            'rating'         => $this->rating,
            'rating_comment' => $this->rating_comment,
            'rated_at'       => $this->rated_at,

            // Relasi
            'staff'          => new StaffResource($this->whenLoaded('staff')),
            'order'          => new OrderResource($this->whenLoaded('order')),
            'documents'      => $this->whenLoaded('visitDocuments', fn() =>
                $this->visitDocuments->map(fn($doc) => [
                    'id'            => $doc->id,
                    'url'           => asset("storage/{$doc->file_path}"),
                    'file_name'     => $doc->file_name,
                    'document_type' => $doc->document_type,
                    'caption'       => $doc->caption,
                ])
            ),

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled'   => 'Terjadwal',
            'on_the_way'  => 'Dalam Perjalanan',
            'arrived'     => 'Tiba di Lokasi',
            'in_progress' => 'Sedang Ditangani',
            'completed'   => 'Selesai',
            'no_show'     => 'Pasien Tidak Ada',
            'cancelled'   => 'Dibatalkan',
            default       => ucfirst($this->status),
        };
    }
}
