<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice_id',
    'additional_charge_id',
    'amount',
    'notes',
])]
class InvoiceAdditionalCharge extends Model
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function additionalCharge()
    {
        return $this->belongsTo(AdditionalCharge::class);
    }
}
