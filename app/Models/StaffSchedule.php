<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'staff_id',
    'day_of_week',
    'start_time',
    'end_time',
    'is_active',
])]
class StaffSchedule extends Model
{
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
