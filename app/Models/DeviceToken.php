<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
}
