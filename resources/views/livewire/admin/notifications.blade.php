<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Notifications</h1>
    @if (session('ok')) <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-2 text-sm">{{ session('ok') }}</div> @endif
    <form wire:submit="send" class="bg-white rounded-xl border p-5 space-y-3 mb-6 max-w-2xl">
        <input wire:model="title" placeholder="Title" class="w-full rounded-lg border px-3 py-2">
        <textarea wire:model="body" rows="3" placeholder="Message" class="w-full rounded-lg border px-3 py-2"></textarea>
        <div class="flex flex-wrap gap-3 items-center">
            <select wire:model="type" class="rounded-lg border px-3 py-2 text-sm">
                <option value="new_content">New content</option><option value="announcement">Announcement</option>
                <option value="premium">Premium subscribers only</option><option value="collection">Collection / journey</option>
            </select>
            <input type="datetime-local" wire:model="scheduled_at" class="rounded-lg border px-3 py-2 text-sm">
            <span class="text-xs text-slate-500">Leave empty to send right away.</span>
        </div>
        @foreach (['title', 'body'] as $f) @error($f)<p class="text-sm text-red-600">{{ $message }}</p>@enderror @endforeach
        <button class="rounded-lg bg-[#0B2A5B] text-white px-5 py-2 text-sm font-semibold">Send / schedule</button>
    </form>
    <div class="bg-white rounded-xl border divide-y max-w-2xl">
        @foreach ($items as $n)
            <div class="p-3 text-sm"><div class="font-medium">{{ $n->title }} <span class="text-xs text-slate-400">· {{ $n->type }} · {{ $n->status }} · {{ $n->scheduled_at?->format('d M H:i') }}</span></div><div class="text-slate-600">{{ $n->body }}</div></div>
        @endforeach
    </div>
</div>
