<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'record_number',
    'patient_id',
    'order_id',
    'doctor_id',
    'diagnosis_primary',
    'diagnosis_secondary',
    'icd10_code',
    'episode_start_date',
    'episode_end_date',
    'treatment_plan',
    'doctor_instructions',
    'diet_instruction',
    'activity_restriction',
    'status',
    'closure_notes',
])]
class MedicalRecord extends Model
{
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Staff::class, 'doctor_id');
    }

    public function progressNotes()
    {
        return $this->hasMany(PatientProgressNote::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function visits()
    {
        return $this->hasManyThrough(Visit::class, Order::class);
    }
}
