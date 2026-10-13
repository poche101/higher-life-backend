<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-serif text-[#0B2A5B]">Affirmations</h1>
        <button wire:click="create" class="rounded-lg bg-[#0B2A5B] text-white px-4 py-2 text-sm font-semibold">+ New affirmation</button>
    </div>

    @if (session('ok')) <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-2 text-sm">{{ session('ok') }}</div> @endif

    <input wire:model.live.debounce.300ms="search" placeholder="Search title or text…" class="w-full md:w-80 rounded-lg border px-3 py-2 mb-4 bg-white">

    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#0B2A5B] text-white text-left"><tr><th class="p-3">Title</th><th>Type</th><th>Category</th><th>Access</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($items as $a)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $a->title }}</td>
                    <td class="capitalize">{{ $a->content_type }}</td>
                    <td>{{ $a->category?->name ?? '—' }}</td>
                    <td>{!! $a->is_premium ? '<span class="text-[#B98B2E] font-semibold">Premium</span>' : 'Free' !!}</td>
                    <td class="capitalize">{{ $a->status }}</td>
                    <td class="text-right pr-3 space-x-3">
                        <button wire:click="edit({{ $a->id }})" class="text-[#0B2A5B] font-medium">Edit</button>
                        <button wire:click="delete({{ $a->id }})" wire:confirm="Delete this affirmation?" class="text-red-600">Delete</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>

    @if ($showForm)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
        <form wire:submit="save" class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
            <h2 class="text-xl font-serif text-[#0B2A5B]">{{ $editingId ? 'Edit' : 'New' }} affirmation</h2>

            <div><label class="text-sm font-medium">Title</label><input wire:model="title" class="w-full rounded-lg border px-3 py-2">@error('title')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="text-sm font-medium">Description</label><input wire:model="description" class="w-full rounded-lg border px-3 py-2"></div>
            <div class="grid grid-cols-2 gap-4">
                @if (config('higherlife.text_only', true))
                    <div><label class="text-sm font-medium">Type</label>
                        <div class="w-full rounded-lg border bg-slate-50 px-3 py-2 text-slate-500">Text</div></div>
                @else
                    <div><label class="text-sm font-medium">Type</label>
                        <select wire:model.live="content_type" class="w-full rounded-lg border px-3 py-2"><option value="text">Text</option><option value="audio">Audio</option><option value="video">Video</option></select></div>
                @endif
                <div><label class="text-sm font-medium">Category</label>
                    <select wire:model="category_id" class="w-full rounded-lg border px-3 py-2"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            </div>
            <div><label class="text-sm font-medium">Affirmation text</label><textarea wire:model="content" rows="6" class="w-full rounded-lg border px-3 py-2"></textarea>@error('content')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="text-sm font-medium">Author</label><input wire:model="author" class="w-full rounded-lg border px-3 py-2"></div>

            <div><label class="text-sm font-medium">Thumbnail</label><input type="file" wire:model="thumbnailFile" accept="image/*" class="block text-sm"></div>

            @if ($content_type !== 'text' && ! config('higherlife.text_only', true))
            <div class="rounded-lg bg-[#F4F1FA] p-4 space-y-3">
                <label class="text-sm font-medium">{{ ucfirst($content_type) }} file</label>
                <input type="file" wire:model="mediaFile" accept="{{ $content_type === 'audio' ? 'audio/*' : 'video/*' }}" class="block text-sm">
                <div wire:loading wire:target="mediaFile" class="text-sm text-[#B98B2E]">Uploading…</div>
                <div><label class="text-sm font-medium">Duration (seconds)</label><input type="number" wire:model="duration" class="w-40 rounded-lg border px-3 py-2 ml-2"></div>
            </div>
            @endif

            <div class="flex gap-6 items-center">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_premium"> Premium</label>
                <select wire:model="status" class="rounded-lg border px-3 py-2 text-sm"><option value="draft">Draft</option><option value="published">Published</option></select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm">Cancel</button>
                <button class="rounded-lg bg-[#0B2A5B] text-white px-5 py-2 text-sm font-semibold">Save</button>
            </div>
        </form>
    </div>
    @endif
</div>
