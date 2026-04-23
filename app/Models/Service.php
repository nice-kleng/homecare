<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'service_category_id',
    'specialization_id',
    'code',
    'name',
    'description',
    'procedure_notes',
    'duration_minutes',
    'base_price',
    'transport_fee',
    'requires_referral',
    'includes_consumables',
    'is_active',
    'sort_order',
])]
class Service extends Model
{
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class);
    }

    public function packageItems()
    {
        return $this->hasMany(ServicePackageItem::class);
    }
}
