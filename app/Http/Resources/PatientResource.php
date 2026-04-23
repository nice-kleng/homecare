<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'no_rekam_medis'  => $this->no_rekam_medis,
            'nik'             => $this->when(
                $request->user()?->hasAnyRole(['admin', 'dokter']),
                $this->nik
            ),
            'name'            => $this->name,
            'gender'          => $this->gender,
            'gender_label'    => $this->gender === 'laki-laki' ? 'Laki-laki' : 'Perempuan',
            'birth_date'      => $this->birth_date,
            'age'             => $this->birth_date
                ? \Carbon\Carbon::parse($this->birth_date)->age
                : null,
            'blood_type'      => $this->blood_type,
            'phone'           => $this->phone,

            // Alamat
            'address'         => $this->address,
            'rt'              => $this->rt,
            'rw'              => $this->rw,
            'postal_code'     => $this->postal_code,
            'coordinates'     => [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ],
            'city'            => $this->whenLoaded('city', fn() => $this->city?->name),
            'district'        => $this->whenLoaded('district', fn() => $this->district?->name),
            'village'         => $this->whenLoaded('village', fn() => $this->village?->name),

            // Kontak darurat
            'emergency_contact' => [
                'name'     => $this->emergency_contact_name,
                'relation' => $this->emergency_contact_relation,
                'phone'    => $this->emergency_contact_phone,
            ],

            // Asuransi
            'insurance' => [
                'type'   => $this->insurance_type,
                'number' => $this->insurance_number,
                'name'   => $this->insurance_name,
            ],

            // Rekam medis (hanya untuk admin/dokter/petugas)
            'medical_alerts' => $this->when(
                $request->user()?->hasAnyRole(['admin', 'dokter', 'petugas']),
                [
                    'allergies'           => $this->allergies,
                    'chronic_diseases'    => $this->chronic_diseases,
                    'current_medications' => $this->current_medications,
                    'medical_notes'       => $this->medical_notes,
                ]
            ),

            // Statistik (lazy-loaded)
            'total_orders'  => $this->whenLoaded('orders', fn() => $this->orders->count()),
            'active_episode'=> $this->whenLoaded('medicalRecords', fn() =>
                $this->medicalRecords->where('status', 'active')->first()?->record_number
            ),

            'status'     => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
