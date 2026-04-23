<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
])]
class Province extends Model
{
    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
