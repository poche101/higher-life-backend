<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    protected $fillable = ['title', 'description', 'thumbnail', 'is_premium', 'status'];

    protected function casts(): array { return ['is_premium' => 'boolean']; }

    public function affirmations(): BelongsToMany
    {
        return $this->belongsToMany(Affirmation::class, 'collection_items')->withPivot('position')->orderByPivot('position');
    }
}
