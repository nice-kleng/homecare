<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'payment_number',
    'invoice_id',
    'patient_id',
    'amount',
    'payment_date',
    'payment_method',
    'bank_name',
    'account_number',
    'transfer_reference',
    'proof_file',
    'status',
    'verified_by',
    'verified_at',
    'verification_notes',
    'notes',
    'received_by',
])]
class Payment extends Model
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
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
