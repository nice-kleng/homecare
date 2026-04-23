<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'service_package_id',
    'service_id',
    'quantity',
])]
class ServicePackageItem extends Model
{
    public function package()
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
