<?php

namespace App\Services\Payments;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * "Pay with Espees" button flow.
 *
 *   1. The app opens our signed checkout page (route payment.checkout), which embeds the Espees button
 *      generated in the Espees Merchant dashboard (button.js + Espeespaybutton("<button id>")).
 *   2. The user clicks it, logs in to their Espees wallet on Espees' page and pays.
 *   3. Espees sends the browser to our Success URL / Fail URL (/payment/success, /payment/failed).
 *   4. Laravel asks Espees to CONFIRM the payment (POST /v2/payment/confirm/ with payment_ref, x-api-key header).
 *      Only an APPROVED answer with the right product SKU and price activates the subscription.
 *
 * Nothing about the browser redirect is trusted: it only supplies a payment_ref that Espees itself must confirm.
 */
class EspeeGateway implements PaymentGateway
{
    public function initialize(Transaction $tx, User $user): array
    {
        if ((string) config('espee.button_id') === '' || (string) config('espee.api_key') === '') {
            throw new RuntimeException('Espees is not configured: set ESPEE_BUTTON_ID and ESPEE_API_KEY.');
        }

        return [
            'checkout_url' => URL::temporarySignedRoute('payment.checkout', now()->addHours(3), ['reference' => $tx->reference]),
            'gateway_reference' => null,   // Espees' payment_ref only exists after the user pays
        ];
    }

    public function verify(string $reference): array
    {
        $pending = ['status' => 'pending', 'amount' => null, 'currency' => null];

        $tx = Transaction::where('reference', $reference)->first();
        if (! $tx || ! $tx->gateway_ref) return $pending;      // no Espees payment has been linked yet

        $key = (string) config('espee.api_key');
        if ($key === '') {
            Log::error('Espees confirm skipped: ESPEE_API_KEY is empty.');
            return $pending;
        }

        try {
            $res = Http::withHeaders(['x-api-key' => $key])->acceptJson()->asJson()
                ->baseUrl(rtrim((string) config('espee.api_base'), '/'))->timeout(20)
                ->post('/v2/payment/confirm/', ['payment_ref' => $tx->gateway_ref])->json();
        } catch (ConnectionException $e) {
            Log::warning('Espees confirm unreachable', ['reference' => $reference, 'error' => $e->getMessage()]);
            return $pending;
        }

        $status = strtoupper(trim((string) data_get($res, 'transaction_status')));
        $sku = data_get($res, 'product_sku');

        return [
            'status' => match ($status) {
                'APPROVED' => 'success',
                'DECLINE', 'DECLINED' => 'failed',
                default => 'pending',        // PENDING, NOT FOUND or anything unexpected: never activate
            },
            'amount' => data_get($res, 'price') !== null ? (float) data_get($res, 'price') : null,
            'currency' => null,              // Espees prices are always in ESP
            // PaymentService only activates when this equals our transaction reference. The button uses ONE fixed SKU,
            // so we return our reference when the SKU is the expected one, and the foreign SKU otherwise (-> admin review).
            'reference' => $sku === config('espee.product_sku') ? $reference : (string) $sku,
            'details' => array_filter([
                'customer_username' => data_get($res, 'customer_username'),
                'product_sku' => $sku,
                'transaction_status' => $status ?: null,
                'status_details' => data_get($res, 'status_details'),
                'transaction_date' => data_get($res, 'transaction_date'),
            ]),
        ];
    }

    /** Espees documents no webhook: confirmation is always pulled with the Confirm Payment API. */
    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
