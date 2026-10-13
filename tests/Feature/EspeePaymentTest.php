<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Pay with Espees" button flow. Espees' Confirm Payment API is faked with the documented response shape.
 *   php artisan test --filter=EspeePaymentTest
 */
class EspeePaymentTest extends TestCase
{
    use RefreshDatabase;

    /** What Espees' Confirm Payment API will answer with (change it inside a test before the action). */
    protected array $confirm = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'espee.driver' => 'espee', 'espee.api_key' => 'KEY-123', 'espee.button_id' => 'BTN-TEST-ID',
            'espee.product_sku' => 'HLIFE-MONTHLY-001', 'espee.api_base' => 'https://api.espees.org',
        ]);
        $this->setConfirm([]);
        Http::fake(['api.espees.org/v2/payment/confirm*' => fn () => Http::response($this->confirm)]);
        $this->seed();
    }

    protected function setConfirm(array $overrides): void
    {
        $this->confirm = array_merge([
            'customer_username' => 'ada_espees', 'product_sku' => 'HLIFE-MONTHLY-001', 'narration' => 'Higher Life Monthly Subscription',
            'price' => 1.0, 'transaction_status' => 'APPROVED', 'status_details' => 'Successfully Done', 'transaction_date' => '18-01-2023 5:50:23',
        ], $overrides);
    }

    protected function member(string $email = 'ada@example.com'): array
    {
        $r = $this->postJson('/api/v1/auth/register', ['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => $email, 'password' => 'password123'])->assertCreated();

        return [User::where('email', $email)->first(), ['Authorization' => 'Bearer '.$r->json('token')]];
    }

    protected function subscribe(array $h): array
    {
        $r = $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertCreated();

        return [$r, Transaction::where('reference', $r->json('reference'))->firstOrFail()];
    }

    /**
     * The test app keeps the Sanctum guard's cached user between requests inside one test.
     * Call this before a request made with a different user's token (production never has this problem:
     * each request is a fresh process).
     */
    protected function switchUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** The browser coming back from Espees: same browser (cookie) that opened the checkout page. */
    protected function back(Transaction $tx, string $page = 'success', ?string $paymentRef = 'PAYREF1234567890')
    {
        $q = $paymentRef ? '?payment_ref='.$paymentRef : '';

        return $this->withCookie('hl_pay', $tx->reference)->get('/payment/'.$page.$q);
    }

    public function test_subscribe_returns_a_signed_checkout_page_and_makes_no_espees_call(): void
    {
        [$u, $h] = $this->member();

        [$r, $tx] = $this->subscribe($h);

        $r->assertJsonPath('mode', 'checkout');
        $this->assertStringContainsString('/pay/'.$tx->reference, $r->json('checkout_url'));
        $this->assertStringContainsString('signature=', $r->json('checkout_url'));
        $this->assertNull($tx->gateway_ref);
        $this->assertSame('espee', $tx->gateway);
        $this->assertFalse($u->fresh()->isPremium());
        Http::assertNothingSent();

        // A second tap re-uses the same unpaid attempt instead of piling up pending payments.
        $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertCreated()->assertJsonPath('reference', $tx->reference);
        $this->assertSame(1, Transaction::count());
    }

    public function test_checkout_page_embeds_the_espees_button_and_remembers_the_payment(): void
    {
        [, $h] = $this->member();
        [$r, $tx] = $this->subscribe($h);

        $this->get($r->json('checkout_url'))
            ->assertOk()
            ->assertSee('merchant.espees.org/static/public/button.js', false)
            ->assertSee('Espeespaybutton("BTN-TEST-ID")', false)
            ->assertSee($tx->reference)
            ->assertCookie('hl_pay', $tx->reference);

        // The link only works as signed by us.
        $this->get('/pay/'.$tx->reference)->assertForbidden();
    }

    public function test_without_a_button_id_or_api_key_payments_fail_safely_with_503(): void
    {
        [$u, $h] = $this->member();

        config(['espee.button_id' => '']);
        $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertStatus(503)->assertJsonPath('message', 'Payments are temporarily unavailable. Please try again shortly.');

        config(['espee.button_id' => 'BTN-TEST-ID', 'espee.api_key' => '']);
        $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertStatus(503);

        $this->assertFalse($u->fresh()->isPremium());
    }

    public function test_return_from_espees_activates_only_after_an_approved_and_matching_confirmation(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        $this->back($tx)->assertOk()->assertSee('Payment confirmed')->assertSee($tx->reference);

        $this->assertTrue($u->fresh()->isPremium());
        $tx->refresh();
        $this->assertSame('success', $tx->status);
        $this->assertSame('PAYREF1234567890', $tx->gateway_ref);
        $this->assertSame('ada_espees', data_get($tx->metadata, 'espees.customer_username'));
        $this->getJson('/api/v1/affirmations', $h)->assertOk();

        Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/v2/payment/confirm/') && $q['payment_ref'] === 'PAYREF1234567890' && $q->hasHeader('x-api-key', 'KEY-123'));
    }

    public function test_a_pending_payment_does_not_unlock_and_the_page_keeps_checking(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        $this->setConfirm(['transaction_status' => 'PENDING']);
        $this->back($tx)->assertOk()->assertSee('Confirming your payment')->assertSee('http-equiv="refresh"', false);
        $this->assertFalse($u->fresh()->isPremium());

        // The app's poll finishes the job once Espees says APPROVED.
        $this->setConfirm(['transaction_status' => 'APPROVED']);
        $this->postJson('/api/v1/payments/verify', ['reference' => $tx->reference], $h)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('is_premium', true);
    }

    public function test_wrong_price_or_wrong_product_goes_to_review_and_never_activates(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        $this->setConfirm(['price' => 0.2]);
        $this->back($tx)->assertOk()->assertSee('checking your payment');
        $this->assertSame('review', $tx->fresh()->status);
        $this->assertFalse($u->fresh()->isPremium());

        [$u2, $h2] = $this->member('b@example.com');
        $this->switchUser();
        [, $tx2] = $this->subscribe($h2);
        $this->setConfirm(['product_sku' => 'SOME-OTHER-PRODUCT']);
        $this->back($tx2, 'success', 'PAYREF-OTHER-0001')->assertOk();
        $this->assertSame('review', $tx2->fresh()->status);
        $this->assertFalse($u2->fresh()->isPremium());
    }

    public function test_a_declined_payment_shows_failure_and_stays_locked(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        $this->setConfirm(['transaction_status' => 'DECLINE']);
        $this->back($tx, 'failed')->assertOk()->assertSee('Payment not completed');

        $this->assertSame('failed', $tx->fresh()->status);
        $this->assertFalse($u->fresh()->isPremium());
        $this->getJson('/api/v1/affirmations', $h)->assertStatus(402);
    }

    public function test_one_espees_payment_can_never_unlock_two_accounts(): void
    {
        [$a, $ha] = $this->member('a@example.com');
        [, $txA] = $this->subscribe($ha);
        $this->back($txA, 'success', 'SHARED-PAYREF-0001')->assertSee('Payment confirmed');
        $this->assertTrue($a->fresh()->isPremium());

        [$b, $hb] = $this->member('b@example.com');
        $this->switchUser();
        [, $txB] = $this->subscribe($hb);
        $this->back($txB, 'success', 'SHARED-PAYREF-0001')->assertOk();

        $this->assertFalse($b->fresh()->isPremium());
        $txB->refresh();
        $this->assertSame('review', $txB->status);
        $this->assertSame('payment_ref_reused', data_get($txB->metadata, 'reason'));
        $this->assertNull($txB->gateway_ref);
    }

    public function test_a_return_that_cannot_be_matched_never_activates_anyone(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        // Another browser (no cookie) with a payment_ref: we cannot tell whose payment it is.
        $this->get('/payment/success?payment_ref=PAYREF1234567890')->assertOk()->assertSee('Back from Espees');
        // Our browser, but Espees gave no payment_ref at all.
        $this->withCookie('hl_pay', $tx->reference)->get('/payment/success')->assertOk()->assertSee('Back from Espees');
        // withCookie() sticks for the rest of the test; drop it so the next requests are a cookie-less browser.
        $this->defaultCookies = [];
        // Nothing at all.
        $this->get('/payment/success')->assertOk()->assertSee('Payment status unavailable');
        $this->get('/payment/failed')->assertOk()->assertSee('Payment not completed');

        $this->assertFalse($u->fresh()->isPremium());
        Http::assertNothingSent();

        // Before any payment_ref is linked, the app's poll has nothing to ask Espees about.
        $this->postJson('/api/v1/payments/verify', ['reference' => $tx->reference], $h)->assertOk()->assertJsonPath('status', 'pending');
        Http::assertNothingSent();
    }

    public function test_junk_in_the_url_is_ignored(): void
    {
        $this->get('/payment/success?payment_ref=<script>alert(1)</script>')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/payment/success?payment_ref='.str_repeat('a', 400))->assertOk();
    }

    public function test_a_user_cannot_verify_someone_elses_payment(): void
    {
        [, $h1] = $this->member('a@example.com');
        [, $tx] = $this->subscribe($h1);
        [, $h2] = $this->member('b@example.com');
        $this->switchUser();

        $this->postJson('/api/v1/payments/verify', ['reference' => $tx->reference], $h2)->assertNotFound();
    }

    public function test_user_can_report_a_payment_that_could_not_be_matched_and_an_admin_approves_it(): void
    {
        [$u, $h] = $this->member();
        [, $tx] = $this->subscribe($h);

        $this->postJson('/api/v1/payments/submit', ['reference' => $tx->reference, 'sender_name' => 'ada_espees'], $h)
            ->assertOk()->assertJsonPath('status', 'review');
        $this->assertFalse($u->fresh()->isPremium());

        app(\App\Services\Payments\PaymentService::class)->approve($tx->fresh(), User::where('email', 'admin@higherlife.app')->first());
        $this->assertTrue($u->fresh()->isPremium());
    }

    public function test_reverify_command_only_checks_payments_that_already_have_an_espees_ref(): void
    {
        [$a, $ha] = $this->member('a@example.com');
        [, $txA] = $this->subscribe($ha);
        $txA->update(['gateway_ref' => 'PAYREF-A-000001']);

        [$b, $hb] = $this->member('b@example.com');
        $this->switchUser();
        [, $txB] = $this->subscribe($hb);   // no payment_ref yet

        $this->artisan('payments:reverify')->assertSuccessful();

        $this->assertTrue($a->fresh()->isPremium());
        $this->assertFalse($b->fresh()->isPremium());
    }

    public function test_webhook_endpoint_stays_closed_because_espees_documents_no_webhook(): void
    {
        $this->postJson('/api/v1/payments/webhook', ['reference' => 'HL-ANYTHING'])->assertStatus(401);
    }

    public function test_only_text_affirmations_are_served(): void
    {
        [$u, $h] = $this->member();
        $cat = \App\Models\Category::first();
        \App\Models\Affirmation::create(['title' => 'A video', 'content_type' => 'video', 'status' => 'published', 'published_at' => now(), 'category_id' => $cat->id]);
        \App\Models\Affirmation::create(['title' => 'An audio', 'content_type' => 'audio', 'status' => 'published', 'published_at' => now(), 'category_id' => $cat->id]);
        $tx = $this->subscribe($h)[1];
        $this->back($tx)->assertSee('Payment confirmed');

        $types = collect($this->getJson('/api/v1/affirmations?type=video', $h)->assertOk()->json('data'));
        $this->assertCount(0, $types);
        $all = collect($this->getJson('/api/v1/affirmations', $h)->assertOk()->json('data'));
        $this->assertTrue($all->isNotEmpty());
        $this->assertSame(['text'], $all->pluck('content_type')->unique()->values()->all());
    }
}
