<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,

            // Kategori
            'category' => $this->whenLoaded('serviceCategory', fn() => [
                'id'   => $this->serviceCategory->id,
                'name' => $this->serviceCategory->name,
                'icon' => $this->serviceCategory->icon,
            ]),

            // Spesialisasi yang dibutuhkan
            'specialization' => $this->whenLoaded('specialization', fn() => [
                'id'   => $this->specialization?->id,
                'name' => $this->specialization?->name,
            ]),

            // Operasional
            'duration_minutes'    => $this->duration_minutes,
            'base_price'          => $this->base_price,
            'transport_fee'       => $this->transport_fee,
            'requires_referral'   => $this->requires_referral,
            'includes_consumables'=> $this->includes_consumables,

            // Prosedur (hanya untuk petugas/dokter)
            'procedure_notes' => $this->when(
                $request->user()?->hasAnyRole(['admin', 'dokter', 'petugas']),
                $this->procedure_notes
            ),

            'is_active'  => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
