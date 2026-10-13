<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AffirmationResource;
use App\Models\Affirmation;
use App\Models\UserProgress;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request, ProgressService $svc)
    {
        $user = $request->user();

        $continue = UserProgress::where('user_id', $user->id)->whereNull('completed_at')->where('progress', '>', 0)
            ->whereIn('content_type', ['audio', 'video'])->latest('updated_at')->limit(10)->get();
        $items = Affirmation::published()->with(['category', 'media'])->whereIn('id', $continue->pluck('affirmation_id'))->get()->keyBy('id');

        return response()->json([
            'streak' => $svc->streak($user),
            'completed_total' => UserProgress::where('user_id', $user->id)->whereNotNull('completed_at')->count(),
            'completed_today' => UserProgress::where('user_id', $user->id)->whereDate('completed_at', today())->exists(),
            'continue' => $continue->filter(fn ($p) => $items->has($p->affirmation_id))->map(fn ($p) => [
                'progress' => $p->progress,
                'affirmation' => (new AffirmationResource($items[$p->affirmation_id]))->resolve($request),
            ])->values(),
        ]);
    }

    public function store(Request $request, ProgressService $svc)
    {
        $data = $request->validate(['affirmation_id' => 'required|integer|exists:affirmations,id', 'progress' => 'nullable|integer|min:0|max:100', 'completed' => 'nullable|boolean']);
        $a = Affirmation::published()->findOrFail($data['affirmation_id']);

        $row = $svc->record($request->user(), $a, $data['progress'] ?? 0, (bool) ($data['completed'] ?? false));

        return response()->json(['progress' => $row->progress, 'completed' => $row->completed_at !== null, 'streak' => $svc->streak($request->user())]);
    }
}
