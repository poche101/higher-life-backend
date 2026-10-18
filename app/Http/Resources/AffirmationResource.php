<?php

namespace App\Http\Resources;

use App\Services\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffirmationResource extends JsonResource
{
    /** Premium access is computed once per request, not per item. */
    public static function canAccessPremium(Request $request): bool
    {
        if (! $request->attributes->has('hl_premium')) {
            $u = $request->user('sanctum');
            $request->attributes->set('hl_premium', (bool) ($u && $u->hasAccess()));
        }
        return $request->attributes->get('hl_premium');
    }

    public static function favoriteIds(Request $request): array
    {
        if (! $request->attributes->has('hl_favs')) {
            $u = $request->user('sanctum');
            $request->attributes->set('hl_favs', $u ? $u->favorites()->pluck('affirmations.id')->all() : []);
        }
        return $request->attributes->get('hl_favs');
    }

    public function toArray(Request $request): array
    {
        $locked = ($this->is_premium || $this->resource->getAttribute('force_lock')) && ! self::canAccessPremium($request);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $locked ? null : $this->content,
            'content_type' => $this->content_type,
            'author' => $this->author,
            'thumbnail' => MediaUrl::thumb($this->thumbnail),
            'is_premium' => $this->is_premium,
            'locked' => $locked,
            'is_favorite' => in_array($this->id, self::favoriteIds($request), true),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug,
            ] : null),
            'media' => $locked ? [] : $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'type' => $m->type,
                'url' => MediaUrl::media($m->file_url),
                'duration' => $m->duration,
                'file_size' => $m->file_size,
                'mime_type' => $m->mime_type,
            ])->values()),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
