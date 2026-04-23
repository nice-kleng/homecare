<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'icon',
    'color',
    'description',
    'is_active',
    'sort_order',
])]
class ServiceCategory extends Model
{
    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
