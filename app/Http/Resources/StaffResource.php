<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'employee_id' => $this->employee_id,
            'name'        => $this->whenLoaded('user', fn() => $this->user->name),
            'email'       => $this->when(
                $request->user()?->hasRole('admin'),
                $this->whenLoaded('user', fn() => $this->user->email)
            ),
            'phone'       => $this->phone,
            'gender'      => $this->gender,
            'avatar'      => $this->whenLoaded('user', fn() =>
                $this->user->avatar
                    ? asset("storage/{$this->user->avatar}")
                    : null
            ),

            // Spesialisasi
            'specialization' => $this->whenLoaded('specialization', fn() => [
                'id'   => $this->specialization->id,
                'name' => $this->specialization->name,
                'code' => $this->specialization->code,
            ]),

            // Sertifikasi (hanya untuk admin)
            'certifications' => $this->when(
                $request->user()?->hasRole('admin'),
                [
                    'str_number'    => $this->str_number,
                    'str_expired'   => $this->str_expired_at,
                    'str_valid'     => $this->str_expired_at
                        ? \Carbon\Carbon::parse($this->str_expired_at)->isFuture()
                        : null,
                    'sip_number'    => $this->sip_number,
                    'sip_expired'   => $this->sip_expired_at,
                ]
            ),

            // Operasional
            'max_visits_per_day' => $this->max_visits_per_day,
            'service_radius_km'  => $this->service_radius_km,
            'status'             => $this->status,

            // Jadwal kerja
            'schedules' => $this->whenLoaded('staffSchedules', fn() =>
                $this->staffSchedules->where('is_active', true)->map(fn($s) => [
                    'day'        => $s->day_of_week,
                    'start_time' => $s->start_time,
                    'end_time'   => $s->end_time,
                ])
            ),

            // Stats (dari withCount atau eager load)
            'stats' => [
                'visits_this_month' => $this->visits_this_month ?? null,
                'avg_rating'        => isset($this->avg_rating)
                    ? round($this->avg_rating, 1)
                    : null,
                'visits_today'      => $this->visits_today ?? null,
            ],

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
