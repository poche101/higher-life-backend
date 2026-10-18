<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\FreshAuthPerRequest;

    protected function setUp(): void
    {
        parent::setUp();
        config(['espee.driver' => 'fake', 'higherlife.trial_days' => 7]);
        $this->seed();
    }

    protected function member(): array
    {
        $r = $this->postJson('/api/v1/auth/register', ['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada@example.com', 'password' => 'password123'])->assertCreated();

        return [User::where('email', 'ada@example.com')->first(), ['Authorization' => 'Bearer '.$r->json('token')]];
    }

    public function test_new_user_gets_seven_day_trial_with_access(): void
    {
        [$user, $h] = $this->member();
        $this->assertTrue($user->onTrial());
        $this->assertTrue($user->hasAccess());
        $this->assertFalse($user->isPremium());
        $this->assertSame(7, $user->trialDaysLeft());
        $this->getJson('/api/v1/affirmations', $h)->assertOk();
    }

    public function test_access_ends_when_trial_ends(): void
    {
        [$user, $h] = $this->member();
        $user->update(['trial_ends_at' => now()->subMinute()]);
        $this->assertFalse($user->fresh()->hasAccess());
        $this->getJson('/api/v1/affirmations', $h)->assertStatus(402);
    }

    public function test_paying_during_trial_starts_paid_month_after_trial(): void
    {
        [$user] = $this->member();
        $plan = \App\Models\SubscriptionPlan::first();
        $r = app(PaymentService::class)->initiate($user, $plan);
        app(PaymentService::class)->activate($r['transaction']->fresh());
        $sub = $user->fresh()->activeSubscription();
        $this->assertNotNull($sub);
        $this->assertTrue($sub->expires_at->greaterThan($user->trial_ends_at->copy()->addDays($plan->duration_days - 1)));
    }

    public function test_trial_can_be_disabled(): void
    {
        config(['higherlife.trial_days' => 0]);
        $r = $this->postJson('/api/v1/auth/register', ['first_name' => 'B', 'last_name' => 'C', 'email' => 'b@example.com', 'password' => 'password123'])->assertCreated();
        $this->getJson('/api/v1/affirmations', ['Authorization' => 'Bearer '.$r->json('token')])->assertStatus(402);
    }
}
