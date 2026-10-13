<div>
    <h1 class="text-2xl font-serif text-[#0B2A5B] mb-6">Subscriptions & Payments</h1>
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm p-3">{{ session('status') }}</div>
    @endif
    <div class="flex gap-2 mb-4">
        @foreach (['review' => 'Awaiting confirmation', 'subscriptions' => 'Subscriptions', 'transactions' => 'Transactions'] as $key => $label)
            <button wire:click="$set('tab','{{ $key }}')" class="rounded-full px-4 py-1.5 text-sm {{ $tab === $key ? 'bg-[#0B2A5B] text-white' : 'bg-white border' }}">{{ $label }}@if ($key === 'review' && $reviewCount > 0)<span class="ml-2 rounded-full bg-red-600 text-white text-xs px-2 py-0.5">{{ $reviewCount }}</span>@endif</button>
        @endforeach
    </div>
    @if ($tab === 'review')
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm p-3">
            Check the Espees account for an incoming transfer of {{ number_format((float) (\App\Models\SubscriptionPlan::where('is_active', true)->value('price') ?? 0), 2) }} {{ config('espee.currency') }} to code <strong>{{ config('espee.transfer_code') }}</strong> carrying the reference before approving.
        </div>
    @endif
    <div class="bg-white rounded-xl border overflow-hidden">
    @if ($tab === 'review')
        <table class="w-full text-sm"><thead class="bg-[#0B2A5B] text-white text-left"><tr><th class="p-3">User</th><th>Reference</th><th>Amount</th><th>Espees txn ID</th><th>Sender name</th><th>Submitted</th><th></th></tr></thead><tbody>
        @forelse ($rows as $t)
            <tr class="border-t" wire:key="r{{ $t->id }}"><td class="p-3">{{ $t->user?->email }}</td><td class="font-mono text-xs">{{ $t->reference }}</td><td>{{ $t->amount }} {{ $t->currency }}</td><td>{{ data_get($t->metadata, 'proof.espees_txn_id') ?: ($t->gateway_ref ?: '—') }}</td><td>{{ data_get($t->metadata, 'proof.sender_name') ?: (data_get($t->metadata, 'espees.customer_username') ?: '—') }}</td><td>{{ data_get($t->metadata, 'proof.submitted_at') ? \Illuminate\Support\Carbon::parse(data_get($t->metadata, 'proof.submitted_at'))->format('d M Y H:i') : '—' }}</td>
                <td class="p-3 whitespace-nowrap"><button wire:click="approve({{ $t->id }})" wire:confirm="Approve this payment and activate the subscription?" class="rounded bg-green-600 text-white px-3 py-1 text-xs">Approve</button> <button wire:click="reject({{ $t->id }})" wire:confirm="Reject this payment?" class="rounded bg-red-600 text-white px-3 py-1 text-xs">Reject</button></td></tr>
        @empty
            <tr><td class="p-6 text-center text-gray-500" colspan="7">Nothing is waiting for confirmation.</td></tr>
        @endforelse</tbody></table>
    @elseif ($tab === 'subscriptions')
        <table class="w-full text-sm"><thead class="bg-[#0B2A5B] text-white text-left"><tr><th class="p-3">User</th><th>Status</th><th>Starts</th><th>Expires</th><th>Auto-renew</th></tr></thead><tbody>
        @foreach ($rows as $s)
            <tr class="border-t"><td class="p-3">{{ $s->user?->email }}</td><td class="capitalize">{{ $s->status }}</td><td>{{ $s->starts_at?->format('d M Y') ?? '—' }}</td><td>{{ $s->expires_at?->format('d M Y') ?? '—' }}</td><td>{{ $s->auto_renew ? 'Yes' : 'No' }}</td></tr>
        @endforeach</tbody></table>
    @else
        <table class="w-full text-sm"><thead class="bg-[#0B2A5B] text-white text-left"><tr><th class="p-3">Reference</th><th>User</th><th>Amount</th><th>Status</th><th>Paid</th><th></th></tr></thead><tbody>
        @foreach ($rows as $t)
            <tr class="border-t" wire:key="t{{ $t->id }}"><td class="p-3 font-mono text-xs">{{ $t->reference }}</td><td>{{ $t->user?->email }}</td><td>{{ $t->amount }} {{ $t->currency }}</td><td class="capitalize">{{ $t->status }}</td><td>{{ $t->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                <td class="p-3 whitespace-nowrap">@if (in_array($t->status, ['pending', 'review']))<button wire:click="approve({{ $t->id }})" wire:confirm="Approve this payment and activate the subscription?" class="rounded bg-green-600 text-white px-3 py-1 text-xs">Approve</button> <button wire:click="reject({{ $t->id }})" wire:confirm="Reject this payment?" class="rounded bg-red-600 text-white px-3 py-1 text-xs">Reject</button>@endif</td></tr>
        @endforeach</tbody></table>
    @endif
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
