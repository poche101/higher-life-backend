<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Manual Espees transfer. The user sends Espees to the configured recipient code (optionally via a pay link);
 * there is no Espees API to query, so an admin confirms the payment in the admin panel (PaymentService::approve()).
 */
class TransferGateway implements PaymentGateway
{
    public function initialize(Transaction $tx, User $user): array
    {
        return ['checkout_url' => (string) config('espee.pay_url'), 'gateway_reference' => $tx->reference];
    }

    /**
     * Always 'pending': confirmation is manual and there is nothing to ask a remote service.
     * A transaction only becomes 'success' when an admin approves it.
     */
    public function verify(string $reference): array
    {
        return ['status' => 'pending', 'amount' => null, 'currency' => null];
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
