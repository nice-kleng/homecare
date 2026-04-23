<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'code',
    'description',
])]
class Specialization extends Model
{
    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}
