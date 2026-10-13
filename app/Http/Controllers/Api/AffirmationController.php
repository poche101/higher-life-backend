<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AffirmationResource;
use App\Models\Affirmation;
use App\Models\DailyAffirmation;
use Illuminate\Http\Request;

class AffirmationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => 'nullable|in:text,audio,video', 'category' => 'nullable|string', 'premium' => 'nullable|boolean',
            'q' => 'nullable|string|max:100', 'sort' => 'nullable|in:recent,popular',
        ]);

        $query = Affirmation::published()->with(['category', 'media']);

        if ($t = $request->type) $query->where('content_type', $t);
        if ($c = $request->category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $c)->orWhere('id', is_numeric($c) ? $c : 0));
        }
        if ($request->has('premium')) $query->where('is_premium', $request->boolean('premium'));

        if ($s = trim((string) $request->q)) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%';
            $query->where(fn ($q) => $q
                ->where('title', 'like', $like)->orWhere('content', 'like', $like)->orWhere('author', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('collections', fn ($c) => $c->where('title', 'like', $like)));
        }

        $request->sort === 'popular'
            ? $query->withCount('favoritedBy')->orderByDesc('favorited_by_count')
            : $query->latest('published_at');

        return AffirmationResource::collection($query->paginate(20));
    }

    public function show(Request $request, int $affirmation)
    {
        $item = Affirmation::published()->with(['category', 'media'])->findOrFail($affirmation);
        return new AffirmationResource($item);
    }

    public function daily(Request $request)
    {
        $item = DailyAffirmation::whereDate('date', today())->first()?->affirmation()->published()->with(['category', 'media'])->first()
            ?? Affirmation::published()->where('is_premium', false)->with(['category', 'media'])->inRandomOrder()->first();

        abort_unless($item, 404, 'No affirmation available yet.');
        return new AffirmationResource($item);
    }

    public function toggleFavorite(Request $request, int $affirmation)
    {
        $item = Affirmation::published()->findOrFail($affirmation);
        $result = $request->user()->favorites()->toggle($item->id);

        return response()->json(['is_favorite' => count($result['attached']) > 0]);
    }
}
