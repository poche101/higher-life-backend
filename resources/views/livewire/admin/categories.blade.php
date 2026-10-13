<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Categories</h1>
    <form wire:submit="save" class="bg-white rounded-xl border p-4 flex flex-wrap gap-3 items-end mb-6">
        <div><label class="text-xs text-slate-500">Name</label><input wire:model="name" class="block rounded-lg border px-3 py-2"></div>
        <div class="flex-1 min-w-48"><label class="text-xs text-slate-500">Description</label><input wire:model="description" class="block w-full rounded-lg border px-3 py-2"></div>
        <label class="text-sm flex items-center gap-2 pb-2"><input type="checkbox" wire:model="is_active"> Active</label>
        <button class="rounded-lg bg-[#0B2A5B] text-white px-4 py-2 text-sm font-semibold">{{ $editingId ? 'Update' : 'Add' }}</button>
        @if ($editingId)<button type="button" wire:click="resetForm" class="text-sm pb-2">Cancel</button>@endif
        @error('name')<p class="text-sm text-red-600 w-full">{{ $message }}</p>@enderror
    </form>
    <div class="bg-white rounded-xl border divide-y">
        @foreach ($categories as $c)
            <div class="flex items-center justify-between p-3 text-sm">
                <div><span class="font-medium">{{ $c->name }}</span> <span class="text-slate-400">· {{ $c->affirmations_count }} items</span> @unless($c->is_active)<span class="text-amber-600">(inactive)</span>@endunless</div>
                <div class="space-x-3"><button wire:click="edit({{ $c->id }})" class="text-[#0B2A5B]">Edit</button><button wire:click="delete({{ $c->id }})" wire:confirm="Delete this category?" class="text-red-600">Delete</button></div>
            </div>
        @endforeach
    </div>
</div>
