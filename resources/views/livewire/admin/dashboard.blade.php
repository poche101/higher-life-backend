<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Dashboard</h1>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach ([
            ['Users', number_format($stats['users']), '+'.$stats['new_users'].' this week'],
            ['Active subscriptions', number_format($stats['active_subs']), $stats['expired_subs'].' expired'],
            ['Revenue (all time)', number_format($stats['revenue'], 2).' ESP', $stats['renewals'].' successful payments'],
            ['Published content', number_format($stats['content']), $stats['premium_content'].' premium'],
        ] as [$label, $value, $sub])
            <div class="bg-white rounded-xl border p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="text-2xl font-semibold text-[#0B2A5B] mt-1">{{ $value }}</div>
                <div class="text-xs text-[#B98B2E] mt-1">{{ $sub }}</div>
            </div>
        @endforeach
    </div>
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border p-5">
            <h2 class="font-semibold text-[#0B2A5B] mb-3">Most favorited</h2>
            @forelse ($topFavorited as $a)
                <div class="flex justify-between py-2 border-b last:border-0 text-sm"><span>{{ $a->title }}</span><span class="text-slate-500">{{ $a->favorited_by_count }} ♥</span></div>
            @empty <p class="text-sm text-slate-500">No favorites yet.</p> @endforelse
        </div>
        <div class="bg-white rounded-xl border p-5">
            <h2 class="font-semibold text-[#0B2A5B] mb-3">Popular categories</h2>
            @forelse ($topCategories as $c)
                <div class="flex justify-between py-2 border-b last:border-0 text-sm"><span>{{ $c->name }}</span><span class="text-slate-500">{{ $c->affirmations_count }} items</span></div>
            @empty <p class="text-sm text-slate-500">No categories yet.</p> @endforelse
        </div>
        <div class="bg-white rounded-xl border p-5 lg:col-span-2">
            <h2 class="font-semibold text-[#0B2A5B] mb-3">Engagement</h2>
            <div class="grid grid-cols-3 text-center">
                <div><div class="text-2xl font-semibold text-[#0B2A5B]">{{ $stats['completions_today'] }}</div><div class="text-xs text-slate-500">Completions today</div></div>
                <div><div class="text-2xl font-semibold text-[#0B2A5B]">{{ $stats['active_week'] }}</div><div class="text-xs text-slate-500">Active users (7 days)</div></div>
                <div><div class="text-2xl font-semibold text-[#0B2A5B]">{{ $stats['returning'] }}</div><div class="text-xs text-slate-500">Returning users</div></div>
            </div>
        </div>
    </div>
</div>
