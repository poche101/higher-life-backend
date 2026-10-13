<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Affirmation extends Model
{
    protected $fillable = ['category_id', 'title', 'description', 'content', 'content_type', 'author', 'thumbnail', 'is_premium', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['is_premium' => 'boolean', 'published_at' => 'datetime'];
    }

    /** Text-only mode (config/higherlife.php): video and audio affirmations are hidden everywhere, files untouched. */
    protected static function booted(): void
    {
        static::addGlobalScope('text_only', function (Builder $q) {
            if (config('higherlife.text_only', true)) $q->where($q->getModel()->getTable().'.content_type', 'text');
        });
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function media(): HasMany { return $this->hasMany(AffirmationMedia::class); }
    public function favoritedBy(): BelongsToMany { return $this->belongsToMany(User::class, 'favorites'); }
    public function collections(): BelongsToMany { return $this->belongsToMany(Collection::class, 'collection_items'); }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
