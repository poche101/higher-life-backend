<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Users</h1>
    <input wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="w-full md:w-80 rounded-lg border px-3 py-2 mb-4 bg-white">
    <div class="bg-white rounded-xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#0B2A5B] text-white text-left"><tr><th class="p-3">Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Premium</th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr class="border-t"><td class="p-3 font-medium">{{ $u->first_name }} {{ $u->last_name }}</td><td>{{ $u->email }}</td><td class="capitalize">{{ $u->role }}</td><td>{{ $u->created_at->format('d M Y') }}</td>
                    <td>{!! $u->subscriptions_exists ? '<span class="text-[#B98B2E] font-semibold">Premium</span>' : '—' !!}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
