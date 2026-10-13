<?php

namespace App\Livewire\Admin;

use App\Models\Affirmation;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProgress;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'users' => User::count(),
            'new_users' => User::where('created_at', '>=', now()->subWeek())->count(),
            'active_subs' => Subscription::where('status', 'active')->where('expires_at', '>', now())->count(),
            'expired_subs' => Subscription::where('status', 'expired')->count(),
            'revenue' => (float) Transaction::where('status', 'success')->sum('amount'),
            'renewals' => Transaction::where('status', 'success')->count(),
            'content' => Affirmation::where('status', 'published')->count(),
            'premium_content' => Affirmation::where('status', 'published')->where('is_premium', true)->count(),
            'completions_today' => UserProgress::whereDate('completed_at', today())->count(),
            'active_week' => UserProgress::where('updated_at', '>=', now()->subWeek())->distinct()->count('user_id'),
            'returning' => UserProgress::selectRaw('user_id')->groupBy('user_id')->havingRaw('COUNT(DISTINCT DATE(created_at)) > 1')->get()->count(),
        ];

        return view('livewire.admin.dashboard', [
            'stats' => $stats,
            'topFavorited' => Affirmation::withCount('favoritedBy')->orderByDesc('favorited_by_count')->limit(5)->get(),
            'topCategories' => Category::withCount('affirmations')->orderByDesc('affirmations_count')->limit(5)->get(),
        ]);
    }
}
