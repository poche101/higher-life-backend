<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAffirmation extends Model
{
    protected $fillable = ['date', 'affirmation_id'];

    protected function casts(): array { return ['date' => 'date']; }

    public function affirmation(): BelongsTo { return $this->belongsTo(Affirmation::class); }
}
