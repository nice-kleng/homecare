<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_id',
    'staff_id',
    'scheduled_at',
    'departed_at',
    'arrived_at',
    'started_at',
    'completed_at',
    'checkin_latitude',
    'checkin_longitude',
    'checkout_latitude',
    'checkout_longitude',
    'status',
    'soap_subjective',
    'soap_objective',
    'soap_assessment',
    'soap_plan',
    'vital_temperature',
    'vital_pulse',
    'vital_respiration',
    'vital_blood_pressure',
    'vital_oxygen_saturation',
    'vital_weight',
    'vital_blood_sugar',
    'vital_notes',
    'actions_performed',
    'medications_given',
    'consumables_used',
    'next_visit_recommendation',
    'is_validated',
    'validated_by',
    'validated_at',
    'validation_notes',
    'patient_signature',
    'staff_signature',
    'rating',
    'rating_comment',
    'rated_at',
    'notes',
])]
class Visit extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
