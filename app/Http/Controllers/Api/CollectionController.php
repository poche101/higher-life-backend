<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Affirmation;
use App\Models\Collection;
use App\Models\UserProgress;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    protected function withProgress(Request $request, Collection $c): Collection
    {
        $user = $request->user('sanctum');
        $ids = $c->affirmations()->pluck('affirmations.id');
        $done = $user ? UserProgress::where('user_id', $user->id)->whereIn('affirmation_id', $ids)->whereNotNull('completed_at')->distinct()->count('affirmation_id') : 0;
        $total = $ids->count();

        $c->setAttribute('progress', [
            'completed' => $done, 'total' => $total,
            'percent' => $total ? (int) round($done / $total * 100) : 0,
            'label' => $total ? 'Day '.min($done + 1, $total).' of '.$total : null,
        ]);
        return $c;
    }

    public function index(Request $request)
    {
        $cols = Collection::where('status', 'published')->withCount('affirmations')->latest()->get()
            ->map(fn ($c) => $this->withProgress($request, $c));

        return CollectionResource::collection($cols);
    }

    public function show(Request $request, Collection $collection)
    {
        abort_unless($collection->status === 'published', 404);
        $collection->load(['affirmations' => fn ($q) => $q->published(), 'affirmations.category', 'affirmations.media'])->loadCount('affirmations');

        return new CollectionResource($this->withProgress($request, $collection));
    }

    public function progress(Request $request, Collection $collection, ProgressService $svc)
    {
        $data = $request->validate(['affirmation_id' => 'required|integer', 'progress' => 'nullable|integer|min:0|max:100', 'completed' => 'nullable|boolean']);
        $a = $collection->affirmations()->where('affirmations.id', $data['affirmation_id'])->firstOrFail();

        $svc->record($request->user(), $a, $data['progress'] ?? 100, (bool) ($data['completed'] ?? true));

        return response()->json($this->withProgress($request, $collection)->getAttribute('progress'));
    }
}
