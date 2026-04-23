<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice_number',
    'order_id',
    'patient_id',
    'service_fee',
    'transport_fee',
    'consumables_fee',
    'additional_fee',
    'subtotal',
    'discount_percentage',
    'discount_amount',
    'discount_notes',
    'tax_percentage',
    'tax_amount',
    'total_amount',
    'insurance_coverage',
    'patient_liability',
    'status',
    'issued_date',
    'due_date',
    'notes',
    'created_by',
])]
class Invoice extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function additionalCharges()
    {
        return $this->hasMany(InvoiceAdditionalCharge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
