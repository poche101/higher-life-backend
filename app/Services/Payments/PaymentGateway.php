<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** @return array{checkout_url: ?string, gateway_reference: ?string} */
    public function initialize(Transaction $tx, User $user): array;

    /**
     * Server-to-server check.
     * `reference` (optional) is the merchant reference the gateway has on record (Espees: product_sku);
     * when present, PaymentService refuses to activate unless it matches our transaction.
     *
     * @return array{status: 'success'|'failed'|'pending', amount: ?float, currency: ?string, reference?: ?string}
     */
    public function verify(string $reference): array;

    /** Authenticate an incoming webhook (signature check). Espees documents no webhook, so it returns false. */
    public function verifyWebhook(Request $request): bool;
}
