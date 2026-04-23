<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'district_id',
    'name',
    'postal_code',
])]
class Village extends Model
{
    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }
}
