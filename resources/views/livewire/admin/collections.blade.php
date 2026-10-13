<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Collections & Programs</h1>
    <form wire:submit="save" class="bg-white rounded-xl border p-5 space-y-3 mb-6">
        <div class="grid md:grid-cols-2 gap-3">
            <input wire:model="title" placeholder="Title (e.g. 7-Day Confidence Journey)" class="rounded-lg border px-3 py-2">
            <input wire:model="description" placeholder="Description" class="rounded-lg border px-3 py-2">
        </div>
        @error('title')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="flex gap-6 items-center">
            <label class="text-sm flex items-center gap-2"><input type="checkbox" wire:model="is_premium"> Premium</label>
            <select wire:model="status" class="rounded-lg border px-3 py-2 text-sm"><option value="draft">Draft</option><option value="published">Published</option></select>
        </div>
        <div>
            <div class="text-sm font-medium mb-1">Affirmations in this collection (order = tick order)</div>
            <div class="max-h-48 overflow-y-auto border rounded-lg p-2 grid md:grid-cols-2 gap-1">
                @foreach ($allAffirmations as $a)
                    <label class="text-sm flex items-center gap-2"><input type="checkbox" wire:model="selected" value="{{ $a->id }}"> {{ $a->title }}</label>
                @endforeach
            </div>
        </div>
        <div class="flex gap-3"><button class="rounded-lg bg-[#0B2A5B] text-white px-4 py-2 text-sm font-semibold">{{ $editingId ? 'Update' : 'Create' }} collection</button>
        @if ($editingId)<button type="button" wire:click="resetForm" class="text-sm">Cancel</button>@endif</div>
    </form>
    <div class="bg-white rounded-xl border divide-y">
        @foreach ($collections as $c)
            <div class="flex items-center justify-between p-3 text-sm">
                <div><span class="font-medium">{{ $c->title }}</span> <span class="text-slate-400">· {{ $c->affirmations_count }} items · {{ $c->status }}{{ $c->is_premium ? ' · premium' : '' }}</span></div>
                <div class="space-x-3"><button wire:click="edit({{ $c->id }})" class="text-[#0B2A5B]">Edit</button><button wire:click="delete({{ $c->id }})" wire:confirm="Delete this collection?" class="text-red-600">Delete</button></div>
            </div>
        @endforeach
    </div>
</div>
