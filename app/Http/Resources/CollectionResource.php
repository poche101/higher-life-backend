<?php

namespace App\Http\Resources;

use App\Services\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('affirmations', function () use ($request) {
            if ($this->is_premium) {
                $this->affirmations->each(fn ($a) => $a->setAttribute('force_lock', true));
            }
            return AffirmationResource::collection($this->affirmations)->resolve($request);
        });

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => MediaUrl::thumb($this->thumbnail),
            'is_premium' => $this->is_premium,
            'locked' => $this->is_premium && ! AffirmationResource::canAccessPremium($request),
            'total' => $this->affirmations_count ?? null,
            'progress' => $this->resource->getAttribute('progress'),
            'affirmations' => $items,
        ];
    }
}
