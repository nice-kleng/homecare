<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'notifiable_type',
    'notifiable_id',
    'data',
    'read_at',
])]
class Notification extends Model
{
    //
}
