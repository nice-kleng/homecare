<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'channel',
    'recipient',
    'subject',
    'message',
    'status',
    'provider',
    'provider_message_id',
    'error_message',
    'notifiable_type',
    'notifiable_id',
    'sent_at',
])]
class NotificationLog extends Model
{
    //
}
