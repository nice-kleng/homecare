<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'user_id',
    'no_rekam_medis',
    'nik',
    'name',
    'gender',
    'birth_date',
    'birth_place',
    'blood_type',
    'marital_status',
    'religion',
    'occupation',
    'education',
    'phone',
    'address',
    'rt',
    'rw',
    'village_id',
    'district_id',
    'city_id',
    'province_id',
    'postal_code',
    'latitude',
    'longitude',
    'emergency_contact_name',
    'emergency_contact_relation',
    'emergency_contact_phone',
    'insurance_type',
    'insurance_number',
    'insurance_name',
    'allergies',
    'chronic_diseases',
    'current_medications',
    'medical_notes',
    'status',
])]
class Patient extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
