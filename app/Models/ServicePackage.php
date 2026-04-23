<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'description',
    'total_visits',
    'validity_days',
    'price',
    'discount_amount',
    'is_active',
])]
class ServicePackage extends Model
{
    public function items()
    {
        return $this->hasMany(ServicePackageItem::class);
    }
}
