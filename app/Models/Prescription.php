<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'medical_record_id',
    'visit_id',
    'prescribed_by',
    'drug_name',
    'dosage',
    'frequency',
    'route',
    'duration_days',
    'instructions',
    'is_active',
    'start_date',
    'end_date',
])]
class Prescription extends Model
{
    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function prescribedBy()
    {
        return $this->belongsTo(Staff::class, 'prescribed_by');
    }
}
