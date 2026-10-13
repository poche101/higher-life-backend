<?php

namespace App\Services;

use App\Models\Affirmation;
use App\Models\User;
use App\Models\UserProgress;

class ProgressService
{
    public function record(User $user, Affirmation $a, int $progress, bool $completed): UserProgress
    {
        $row = UserProgress::where('user_id', $user->id)->where('affirmation_id', $a->id)->whereNull('completed_at')->latest()->first()
            ?? new UserProgress(['user_id' => $user->id, 'affirmation_id' => $a->id, 'content_type' => $a->content_type]);

        $row->progress = max(0, min(100, $progress));
        if ($completed || $row->progress >= 100) {
            $row->progress = 100;
            $row->completed_at = now();
        }
        $row->save();

        return $row;
    }

    public function streak(User $user): int
    {
        $days = UserProgress::where('user_id', $user->id)->whereNotNull('completed_at')
            ->orderByDesc('completed_at')->pluck('completed_at')
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())->unique()->values();

        if ($days->isEmpty()) return 0;

        $cursor = now()->startOfDay();
        if ($days->first() !== $cursor->toDateString()) {
            $cursor = $cursor->subDay();            // today not done yet: streak can still be alive from yesterday
            if ($days->first() !== $cursor->toDateString()) return 0;
        }

        $streak = 0;
        foreach ($days as $day) {
            if ($day === $cursor->toDateString()) { $streak++; $cursor = $cursor->subDay(); } else { break; }
        }
        return $streak;
    }
}
