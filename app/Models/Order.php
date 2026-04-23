<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_number',
    'patient_id',
    'service_id',
    'service_package_id',
    'ordered_by',
    'visit_date',
    'visit_time_start',
    'visit_time_end',
    'visit_address',
    'visit_rt',
    'visit_rw',
    'visit_village_id',
    'visit_district_id',
    'visit_city_id',
    'visit_postal_code',
    'visit_latitude',
    'visit_longitude',
    'visit_address_notes',
    'chief_complaint',
    'medical_notes',
    'referral_document',
    'status',
    'source',
    'admin_notes',
    'confirmed_by',
    'confirmed_at',
    'cancellation_reason',
    'cancelled_by',
    'cancelled_at',
    'rescheduled_from_id',
])]
class Order extends Model
{
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePackage()
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function visitVillage()
    {
        return $this->belongsTo(Village::class, 'visit_village_id');
    }

    public function visitDistrict()
    {
        return $this->belongsTo(District::class, 'visit_district_id');
    }

    public function visitCity()
    {
        return $this->belongsTo(City::class, 'visit_city_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(Order::class, 'rescheduled_from_id');
    }
}
