<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice_id',
    'description',
    'item_type',
    'quantity',
    'unit_price',
    'total_price',
])]
class InvoiceItem extends Model
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
