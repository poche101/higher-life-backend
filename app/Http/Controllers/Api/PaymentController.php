<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function initiate(Request $request, PaymentService $payments)
    {
        $plan = SubscriptionPlan::where('is_active', true)->firstOrFail();
        try {
            $r = $payments->initiate($request->user(), $plan);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Payments are temporarily unavailable. Please try again shortly.'], 503);
        }

        return response()->json($payments->payload($r['transaction'], $r['checkout_url']), 201);
    }

    /** The app calls this after checkout; Laravel asks the gateway itself. */
    public function verify(Request $request, PaymentService $payments)
    {
        $data = $request->validate(['reference' => 'required|string']);
        abort_unless(Transaction::where('reference', $data['reference'])->where('user_id', $request->user()->id)->exists(), 404);

        $tx = $payments->verify($data['reference']);

        return response()->json([
            'status' => $tx->status,
            'mode' => config('espee.driver') === 'transfer' ? 'transfer' : 'checkout',
            'is_premium' => $request->user()->fresh()->isPremium(),
            'paid_at' => $tx->paid_at?->toIso8601String(),
        ]);
    }

    /** Transfer mode (or an Espees payment we could not match automatically): the user tells us they paid; an admin then confirms. */
    public function submit(Request $request, PaymentService $payments)
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'espees_txn_id' => 'nullable|string|max:120',
            'sender_name' => 'nullable|string|max:120',
        ]);
        abort_unless(in_array(config('espee.driver'), ['transfer', 'espee'], true), 404);
        $tx = Transaction::where('reference', $data['reference'])->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless(in_array($tx->status, ['pending', 'review'], true), 422, 'This payment can no longer be updated.');

        $tx = $payments->submitProof($tx, $data);

        return response()->json([
            'status' => $tx->status,
            'mode' => config('espee.driver') === 'transfer' ? 'transfer' : 'checkout',
            'message' => 'Thanks! We will confirm your payment shortly and activate your subscription.',
        ]);
    }

    public function history(Request $request)
    {
        $tx = $request->user()->transactions()->latest()->paginate(20);

        return response()->json($tx->through(fn ($t) => [
            'reference' => $t->reference, 'amount' => (float) $t->amount, 'currency' => $t->currency,
            'status' => $t->status, 'paid_at' => $t->paid_at?->toIso8601String(), 'created_at' => $t->created_at->toIso8601String(),
        ]));
    }

    /** Payment provider -> Laravel. Signature checked, then re-verified server-to-server. */
    public function webhook(Request $request, PaymentGateway $gateway, PaymentService $payments)
    {
        abort_unless($gateway->verifyWebhook($request), 401, 'Invalid signature.');

        $reference = $request->input('data.reference') ?? $request->input('reference');
        if ($reference) $payments->verify((string) $reference);

        return response()->json(['received' => true]);
    }

    /** Local-development "checkout page" (fake driver only). */
    public function fakeCheckout(string $reference)
    {
        abort_unless(config('espee.driver') === 'fake', 404);
        abort_unless(Transaction::where('reference', $reference)->exists(), 404);

        Cache::put("fake-paid:{$reference}", true, now()->addHour());

        return response('<body style="font-family:sans-serif;text-align:center;padding:48px"><h2>Payment received ✨</h2><p>Return to the Higher Life app to continue.</p></body>');
    }
}
