<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

/**
 * Pages for the "Pay with Espees" button flow.
 *
 *   Checkout (opened by the app, signed URL):  https://hlife.christembassylz5.org/pay/{reference}
 *   Espees Success URL:                        https://hlife.christembassylz5.org/payment/success
 *   Espees Fail URL:                           https://hlife.christembassylz5.org/payment/failed
 *
 * Anyone can open the success/fail URLs, so they prove nothing. The checkout page remembers which pending
 * Higher Life payment this browser started (encrypted cookie). When Espees sends the browser back with a
 * payment_ref, we link it to that payment (once) and ask Espees to confirm it server-to-server.
 * The subscription is activated only inside PaymentService::verify(), after an APPROVED, matching confirmation.
 */
class PaymentReturnController extends Controller
{
    private const COOKIE = 'hl_pay';

    public function checkout(Request $request, string $reference)
    {
        abort_unless(config('espee.driver') === 'espee', 404);
        $tx = Transaction::where('reference', $reference)->where('gateway', 'espee')->firstOrFail();

        if ($tx->status === 'success') {
            return $this->page('success', $tx->reference, 0, null);
        }

        $buttonId = (string) config('espee.button_id');

        return response()
            ->view('payment.checkout', [
                'reference' => $tx->reference,
                'amount' => rtrim(rtrim(number_format((float) $tx->amount, 2, '.', ''), '0'), '.'),
                'currency' => $tx->currency === 'ESP' ? 'Espee' : $tx->currency,
                'plural' => (float) $tx->amount !== 1.0,
                'productName' => config('espee.product_name'),
                'buttonId' => $buttonId,
                'buttonScript' => config('espee.button_script'),
            ])
            // Remember which pending payment this browser started, so the return from Espees can be matched to it.
            ->cookie(self::COOKIE, $tx->reference, 180, '/', null, $request->isSecure(), true, false, 'lax')
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function success(Request $request, PaymentService $payments)
    {
        return $this->render($request, $payments, 'success');
    }

    public function failed(Request $request, PaymentService $payments)
    {
        return $this->render($request, $payments, 'failed');
    }

    protected function render(Request $request, PaymentService $payments, string $landing)
    {
        $cookieRef = (string) $request->cookie(self::COOKIE, '');
        $paymentRef = trim((string) $request->query('payment_ref', ''));
        $paymentRef = preg_match('/^[A-Za-z0-9_-]{8,128}$/', $paymentRef) ? $paymentRef : '';
        $n = max(0, (int) $request->query('n', 0));

        $tx = null;
        if (config('espee.driver') === 'espee' && preg_match('/^HL-[A-Z0-9]{6,32}$/', $cookieRef)) {
            $tx = Transaction::where('reference', $cookieRef)->where('gateway', 'espee')->first();
        }

        $state = 'unknown';
        if ($tx) {
            try {
                $tx = $paymentRef !== '' ? $payments->claim($tx, $paymentRef) : ($payments->verify($tx->reference) ?? $tx);
            } catch (\Throwable $e) {
                report($e);
            }

            $state = match ($tx->status) {
                'success' => 'success',
                'failed' => 'failed',
                'review' => 'review',
                default => 'pending',
            };

            // Back from Espees, but it gave us no payment reference to confirm: we can't tell which payment this was.
            if ($state === 'pending' && ! $tx->gateway_ref) $state = $landing === 'failed' ? 'failed' : 'unmatched';
            // Espees sent the user to the failure URL and nothing has been approved.
            if ($landing === 'failed' && $state === 'pending') $state = 'failed';
        } elseif ($paymentRef !== '') {
            $state = 'unmatched';   // we know Espees' reference but not which Higher Life payment it belongs to
        } elseif ($landing === 'failed') {
            $state = 'failed';
        }

        $autoRefresh = $state === 'pending' && $n < 20;
        $refreshUrl = $autoRefresh ? $request->url().'?'.http_build_query(array_filter(['payment_ref' => $paymentRef ?: null, 'n' => $n + 1])) : null;

        return $this->page($state, $tx?->reference, $n, $refreshUrl);
    }

    protected function page(string $state, ?string $reference, int $n, ?string $refreshUrl)
    {
        return response()
            ->view('payment.status', [
                'state' => $state,
                'reference' => $reference,
                'appLink' => 'higherlife://app/payment?status='.$state.($reference ? '&ref='.urlencode($reference) : ''),
                'refreshUrl' => $refreshUrl,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
