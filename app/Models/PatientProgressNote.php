<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'medical_record_id',
    'visit_id',
    'staff_id',
    'progress_note',
    'condition_trend',
    'noted_at',
])]
class PatientProgressNote extends Model
{
    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
