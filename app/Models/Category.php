<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function affirmations(): HasMany { return $this->hasMany(Affirmation::class); }
}
