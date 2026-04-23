<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'payment_id',
    'invoice_id',
    'amount',
    'reason',
    'status',
    'processed_by',
    'processed_at',
    'notes',
])]
class Refund extends Model
{
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
