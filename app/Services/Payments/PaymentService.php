<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(protected PaymentGateway $gateway) {}

    /** Response body the app renders (payment instructions in transfer mode). */
    public function payload(Transaction $tx, ?string $checkoutUrl): array
    {
        $transfer = config('espee.driver') === 'transfer';

        return [
            'reference' => $tx->reference,
            'checkout_url' => $checkoutUrl ?: null,
            'amount' => (float) $tx->amount,
            'currency' => $tx->currency,
            'mode' => $transfer ? 'transfer' : 'checkout',
            'status' => $tx->status,
            'transfer' => $transfer ? [
                'code' => config('espee.transfer_code'),
                'pay_url' => config('espee.pay_url') ?: null,
                'kc_pay_url' => config('espee.kc_pay_url') ?: null,
                'note' => $tx->reference,
                'amount' => (float) $tx->amount,
                'currency' => $tx->currency,
            ] : null,
        ];
    }

    /** @return array{transaction: Transaction, checkout_url: ?string} */
    public function initiate(User $user, SubscriptionPlan $plan): array
    {
        // Re-use an unpaid attempt instead of piling up pending subscriptions (transfer and Espees button flows).
        $driver = (string) config('espee.driver');
        if (in_array($driver, ['transfer', 'espee'], true)) {
            $open = Transaction::where('user_id', $user->id)->where('gateway', $driver)
                ->whereIn('status', $driver === 'transfer' ? ['pending', 'review'] : ['pending'])
                ->where('created_at', '>=', now()->subDays(3))
                ->whereHas('subscription', fn ($q) => $q->where('plan_id', $plan->id))
                ->latest('id')->first();
            if ($open) return ['transaction' => $open, 'checkout_url' => $this->gateway->initialize($open, $user)['checkout_url']];
        }

        return DB::transaction(function () use ($user, $plan) {
            $sub = Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'pending', 'auto_renew' => true]);

            $tx = Transaction::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'reference' => 'HL-'.strtoupper(Str::random(14)),
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'gateway' => config('espee.driver'),
                'status' => 'pending',
            ]);

            $init = $this->gateway->initialize($tx, $user);
            $tx->update(['metadata' => ['gateway_reference' => $init['gateway_reference'] ?? null]]);

            return ['transaction' => $tx, 'checkout_url' => $init['checkout_url']];
        });
    }

    /** Ask the gateway directly (never trust the app) and activate the subscription if paid. */
    public function verify(string $reference): ?Transaction
    {
        $tx = Transaction::where('reference', $reference)->first();
        if (! $tx) return null;
        if ($tx->status === 'success') return $tx;

        $result = $this->gateway->verify($reference);

        // Keep what the gateway told us (Espees: payer username, SKU, status text, date) for the admin and for audits.
        if (! empty($result['details'])) {
            $tx->update(['metadata' => array_merge($tx->metadata ?? [], ['espees' => $result['details']])]);
        }

        if ($result['status'] === 'success') {
            $amountOk = ($result['amount'] ?? null) === null || abs($result['amount'] - (float) $tx->amount) < 0.0001;
            $currencyOk = ($result['currency'] ?? null) === null || $result['currency'] === $tx->currency;
            $skuOk = ($result['reference'] ?? null) === null || $result['reference'] === $tx->reference;
            if ($amountOk && $currencyOk && $skuOk) {
                $this->activate($tx);
            } else {
                // Gateway says paid but the details disagree with ours: never activate, hand it to an admin.
                $tx->update(['status' => 'review', 'metadata' => array_merge($tx->metadata ?? [], ['reason' => 'verification_mismatch', 'gateway_result' => $result])]);
            }
        } elseif ($result['status'] === 'failed') {
            $tx->update(['status' => 'failed']);
        }

        return $tx->fresh();
    }

    /**
     * Espees sent the user back with a payment_ref. Link it to this transaction (once, ever: the column is unique),
     * then confirm it with Espees. A payment_ref that already belongs to another transaction is never activated twice.
     */
    public function claim(Transaction $tx, string $paymentRef): Transaction
    {
        $tx = $tx->fresh();
        if (in_array($tx->status, ['success', 'failed'], true)) return $tx;

        if ($tx->gateway_ref !== null && $tx->gateway_ref !== $paymentRef) {
            // A second, different Espees payment for the same pending subscription: a human decides.
            $tx->update(['status' => 'review', 'metadata' => array_merge($tx->metadata ?? [], ['reason' => 'multiple_payment_refs'])]);

            return $tx->fresh();
        }

        if ($tx->gateway_ref === null) {
            try {
                $tx->update(['gateway_ref' => $paymentRef]);
            } catch (UniqueConstraintViolationException $e) {
                // This Espees payment was already used for another subscription.
                $tx = $tx->fresh(); // drop the failed gateway_ref still sitting on the model, or the save below would hit the same error
                $tx->update(['status' => 'review', 'metadata' => array_merge($tx->metadata ?? [], ['reason' => 'payment_ref_reused'])]);

                return $tx->fresh();
            }
        }

        return $this->verify($tx->reference) ?? $tx->fresh();
    }

    /** User says they sent the Espees: park the transaction for admin review. */
    public function submitProof(Transaction $tx, array $proof): Transaction
    {
        $tx->update([
            'status' => 'review',
            'metadata' => array_merge($tx->metadata ?? [], ['proof' => [
                'espees_txn_id' => $proof['espees_txn_id'] ?? null,
                'sender_name' => $proof['sender_name'] ?? null,
                'submitted_at' => now()->toIso8601String(),
            ]]),
        ]);

        return $tx->fresh();
    }

    /** Admin confirmed the transfer. Idempotent. */
    public function approve(Transaction $tx, ?User $admin = null): Transaction
    {
        $tx = $tx->fresh();
        if ($tx->status === 'success') return $tx;

        $this->activate($tx);
        $tx = $tx->fresh();
        $tx->update(['metadata' => array_merge($tx->metadata ?? [], [
            'approved_by' => $admin ? ['id' => $admin->id, 'email' => $admin->email] : null,
            'approved_at' => now()->toIso8601String(),
        ])]);

        return $tx->fresh();
    }

    /** Admin rejected the transfer. Never touches an already-successful transaction. */
    public function reject(Transaction $tx, ?User $admin = null, ?string $reason = null): Transaction
    {
        $tx = $tx->fresh();
        if ($tx->status === 'success') return $tx;

        $tx->update(['status' => 'failed', 'metadata' => array_merge($tx->metadata ?? [], [
            'rejected_by' => $admin ? ['id' => $admin->id, 'email' => $admin->email] : null,
            'rejected_at' => now()->toIso8601String(),
            'reason' => $reason,
        ])]);

        return $tx->fresh();
    }

    /** Idempotent: safe to run from both the webhook and the app's verify call. */
    public function activate(Transaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $tx = Transaction::lockForUpdate()->find($tx->id);
            if ($tx->status === 'success') return;

            $sub = Subscription::with('plan')->find($tx->subscription_id);
            $user = User::find($tx->user_id);

            $current = $user->activeSubscription();
            $start = $current?->expires_at && $current->expires_at->isFuture() ? $current->expires_at : now();
            // Paying during the free trial never wastes trial days: the paid month starts when the trial ends.
            if ($user->trial_ends_at && $user->trial_ends_at->isFuture() && $user->trial_ends_at->greaterThan($start)) {
                $start = $user->trial_ends_at;
            }

            $sub->update([
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $start->copy()->addDays($sub->plan->duration_days),
                'auto_renew' => true,
                'cancelled_at' => null,
            ]);

            // Remaining time on an older subscription was carried forward, so retire it.
            Subscription::where('user_id', $user->id)->where('id', '!=', $sub->id)->where('status', 'active')->update(['status' => 'expired']);

            $tx->update(['status' => 'success', 'paid_at' => now()]);
        });
    }
}
