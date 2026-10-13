<?php

namespace Tests\Feature;

use App\Livewire\Admin\Billing;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['espee.driver' => 'transfer', 'espee.transfer_code' => 'TESTCODE', 'espee.pay_url' => '']);
        $this->seed();
    }

    protected function member(string $email = 'ada@example.com'): array
    {
        $r = $this->postJson('/api/v1/auth/register', ['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => $email, 'password' => 'password123'])->assertCreated();

        return [User::where('email', $email)->first(), ['Authorization' => 'Bearer '.$r->json('token')]];
    }

    protected function admin(): User { return User::where('email', 'admin@higherlife.app')->first(); }

    public function test_subscribe_returns_transfer_instructions_and_does_not_activate(): void
    {
        [$u, $h] = $this->member();
        $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertCreated()
            ->assertJsonPath('mode', 'transfer')->assertJsonPath('transfer.code', 'TESTCODE')
            ->assertJsonPath('checkout_url', null)->assertJsonPath('status', 'pending')
            ->assertJsonStructure(['reference', 'amount', 'currency', 'transfer' => ['code', 'pay_url', 'note', 'amount', 'currency']]);
        $this->assertFalse($u->fresh()->isPremium());
        $this->getJson('/api/v1/affirmations', $h)->assertStatus(402);
    }

    public function test_verify_stays_pending(): void
    {
        [, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $this->postJson('/api/v1/payments/verify', ['reference' => $ref], $h)->assertOk()
            ->assertJsonPath('status', 'pending')->assertJsonPath('mode', 'transfer')->assertJsonPath('is_premium', false);
    }

    public function test_submit_proof_moves_to_review_and_is_repeatable(): void
    {
        [, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $body = ['reference' => $ref, 'espees_txn_id' => 'ESP-123', 'sender_name' => 'Ada Obi'];

        $this->postJson('/api/v1/payments/submit', $body, $h)->assertOk()->assertJsonPath('status', 'review')->assertJsonPath('mode', 'transfer')->assertJsonStructure(['message']);
        $this->postJson('/api/v1/payments/submit', $body, $h)->assertOk()->assertJsonPath('status', 'review');

        $tx = Transaction::where('reference', $ref)->first();
        $this->assertSame('ESP-123', $tx->metadata['proof']['espees_txn_id']);
        $this->assertSame(1, Transaction::count());
        $this->postJson('/api/v1/payments/verify', ['reference' => $ref], $h)->assertOk()->assertJsonPath('status', 'review');
    }

    public function test_resubscribing_reuses_the_same_reference(): void
    {
        [, $h] = $this->member();
        $a = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $b = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $c = $this->postJson('/api/v1/payments/initiate', [], $h)->json('reference');
        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Subscription::count());
    }

    public function test_admin_approval_activates_premium_once(): void
    {
        [$u, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $this->postJson('/api/v1/payments/submit', ['reference' => $ref], $h)->assertOk();
        $tx = Transaction::where('reference', $ref)->first();

        $svc = app(\App\Services\Payments\PaymentService::class);
        $svc->approve($tx, $this->admin());
        $this->assertTrue($u->fresh()->isPremium());
        $this->getJson('/api/v1/affirmations', $h)->assertOk();
        $expires = Subscription::first()->expires_at;
        $this->assertNotNull($tx->fresh()->metadata['approved_at']);
        $this->assertSame($this->admin()->id, $tx->fresh()->metadata['approved_by']['id']);

        $svc->approve($tx->fresh(), $this->admin());
        $this->assertEquals($expires, Subscription::first()->fresh()->expires_at);
        $this->assertSame(1, Subscription::where('status', 'active')->count());
        $this->postJson('/api/v1/payments/submit', ['reference' => $ref], $h)->assertStatus(422);
    }

    public function test_reject_marks_failed_and_user_stays_locked(): void
    {
        [$u, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $tx = Transaction::where('reference', $ref)->first();

        app(\App\Services\Payments\PaymentService::class)->reject($tx, $this->admin(), 'No transfer found');
        $this->assertSame('failed', $tx->fresh()->status);
        $this->assertSame('No transfer found', $tx->fresh()->metadata['reason']);
        $this->assertFalse($u->fresh()->isPremium());
        $this->getJson('/api/v1/affirmations', $h)->assertStatus(402);

        // A fresh attempt is possible afterwards.
        $new = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $this->assertNotSame($ref, $new);
    }

    public function test_reject_does_not_touch_successful_transactions(): void
    {
        [$u, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $svc = app(\App\Services\Payments\PaymentService::class);
        $svc->approve(Transaction::where('reference', $ref)->first(), $this->admin());
        $svc->reject(Transaction::where('reference', $ref)->first(), $this->admin());
        $this->assertSame('success', Transaction::where('reference', $ref)->first()->status);
        $this->assertTrue($u->fresh()->isPremium());
    }

    public function test_billing_component_is_admin_only(): void
    {
        [$u, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $this->postJson('/api/v1/payments/submit', ['reference' => $ref, 'espees_txn_id' => 'X1'], $h)->assertOk();
        $tx = Transaction::where('reference', $ref)->first();

        Livewire::actingAs($u)->test(Billing::class)->assertStatus(403);
        $this->assertSame('review', $tx->fresh()->status);

        Livewire::actingAs($this->admin())->test(Billing::class)
            ->assertSee('Awaiting confirmation')->assertSee($ref)->assertSee('TESTCODE')
            ->call('approve', $tx->id)->assertSee('Payment approved');
        $this->assertSame('success', $tx->fresh()->status);
        $this->assertTrue($u->fresh()->isPremium());
    }

    public function test_admin_can_reject_from_component(): void
    {
        [$u, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');
        $tx = Transaction::where('reference', $ref)->first();
        Livewire::actingAs($this->admin())->test(Billing::class)->set('tab', 'transactions')->call('reject', $tx->id);
        $this->assertSame('failed', $tx->fresh()->status);
    }

    public function test_foreign_user_cannot_submit_proof_for_someone_elses_reference(): void
{
    [, $h] = $this->member();
    [, $h2] = $this->member('bob@example.com');
    $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->json('reference');

    $this->app['auth']->forgetGuards(); // drop Ada's cached user so Bob's token is used

    $this->postJson('/api/v1/payments/submit', ['reference' => $ref], $h2)->assertNotFound();
    $this->assertSame('pending', Transaction::where('reference', $ref)->first()->status);
}

    public function test_submit_is_rejected_outside_transfer_mode(): void
    {
        config(['espee.driver' => 'fake']);
        [, $h] = $this->member();
        $ref = $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertJsonPath('mode', 'checkout')->assertJsonPath('transfer', null)->json('reference');
        $this->postJson('/api/v1/payments/submit', ['reference' => $ref], $h)->assertStatus(404);
    }
}
