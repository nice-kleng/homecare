<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'record_number' => $this->record_number,
            'status'        => $this->status,
            'status_label'  => match ($this->status) {
                'active'   => 'Aktif',
                'closed'   => 'Ditutup',
                'referred' => 'Dirujuk',
                default    => ucfirst($this->status),
            },

            // Relasi
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'doctor'  => new StaffResource($this->whenLoaded('doctor')),

            // Diagnosis
            'diagnosis' => [
                'primary'   => $this->diagnosis_primary,
                'secondary' => $this->diagnosis_secondary,
                'icd10'     => $this->icd10_code,
            ],

            // Instruksi & rencana
            'treatment_plan'       => $this->treatment_plan,
            'doctor_instructions'  => $this->doctor_instructions,
            'diet_instruction'     => $this->diet_instruction,
            'activity_restriction' => $this->activity_restriction,

            // Periode episode
            'episode_start_date' => $this->episode_start_date,
            'episode_end_date'   => $this->episode_end_date,
            'duration_days'      => $this->episode_start_date
                ? \Carbon\Carbon::parse($this->episode_start_date)
                    ->diffInDays($this->episode_end_date ?? now())
                : null,

            // Resep aktif
            'prescriptions' => $this->whenLoaded('prescriptions', fn() =>
                $this->prescriptions->where('is_active', true)->map(fn($rx) => [
                    'id'           => $rx->id,
                    'drug_name'    => $rx->drug_name,
                    'dosage'       => $rx->dosage,
                    'frequency'    => $rx->frequency,
                    'route'        => $rx->route,
                    'duration_days'=> $rx->duration_days,
                    'instructions' => $rx->instructions,
                    'start_date'   => $rx->start_date,
                    'end_date'     => $rx->end_date,
                ])
            ),

            // Progress notes
            'progress_notes' => $this->whenLoaded('progressNotes', fn() =>
                $this->progressNotes->map(fn($note) => [
                    'id'              => $note->id,
                    'noted_at'        => $note->noted_at,
                    'progress_note'   => $note->progress_note,
                    'condition_trend' => $note->condition_trend,
                    'staff'           => $note->staff?->user?->name,
                ])
            ),

            'closure_notes' => $this->closure_notes,
            'created_at'    => $this->created_at->toDateTimeString(),
            'updated_at'    => $this->updated_at->toDateTimeString(),
        ];
    }
}
