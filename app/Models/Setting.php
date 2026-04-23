<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'value',
    'type',
    'group',
    'label',
    'description',
    'is_public',
])]
class Setting extends Model
{
    //
}
