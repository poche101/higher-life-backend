<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['user_id', 'subscription_id', 'reference', 'gateway_ref', 'amount', 'currency', 'gateway', 'status', 'paid_at', 'metadata'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'metadata' => 'array', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}
