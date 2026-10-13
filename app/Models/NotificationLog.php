<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = ['user_id', 'notification_id', 'sent_at', 'status'];

    protected function casts(): array { return ['sent_at' => 'datetime']; }
}
