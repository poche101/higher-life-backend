<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'price', 'currency', 'billing_interval', 'duration_days', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2'];
    }
}
