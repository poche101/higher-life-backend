<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';
    protected $fillable = ['title', 'body', 'type', 'scheduled_at', 'status'];

    protected function casts(): array { return ['scheduled_at' => 'datetime']; }
}
