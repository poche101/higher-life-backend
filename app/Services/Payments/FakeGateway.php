<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Local-development gateway: open the checkout URL to "pay", then verify succeeds. */
class FakeGateway implements PaymentGateway
{
    public function initialize(Transaction $tx, User $user): array
    {
        return ['checkout_url' => route('payments.fake-checkout', $tx->reference), 'gateway_reference' => $tx->reference];
    }

    public function verify(string $reference): array
    {
        $paid = Cache::get("fake-paid:{$reference}", false);
        return ['status' => $paid ? 'success' : 'pending', 'amount' => null, 'currency' => null];
    }

    public function verifyWebhook(Request $request): bool
    {
        return app()->environment('local', 'testing');
    }
}
