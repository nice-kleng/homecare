<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'user_id',
    'specialization_id',
    'employee_id',
    'str_number',
    'str_expired_at',
    'sip_number',
    'sip_expired_at',
    'nik',
    'gender',
    'birth_date',
    'address',
    'city_id',
    'phone',
    'service_radius_km',
    'latitude',
    'longitude',
    'max_visits_per_day',
    'status',
    'notes',
])]
class Staff extends Model
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

    public function specialization()
    {
        return $this->belongsTo(Specialization::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function schedules()
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function leaves()
    {
        return $this->hasMany(StaffLeave::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
