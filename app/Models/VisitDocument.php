<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'visit_id',
    'file_path',
    'file_name',
    'file_type',
    'document_type',
    'caption',
    'uploaded_by',
])]
class VisitDocument extends Model
{
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
