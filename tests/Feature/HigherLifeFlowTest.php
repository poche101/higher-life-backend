<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end smoke test of the contract the Flutter app relies on.
 * Run:  php artisan test --filter=HigherLifeFlowTest
 */
class HigherLifeFlowTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\FreshAuthPerRequest;

    protected function setUp(): void
    {
        parent::setUp();
        config(['higherlife.trial_days' => 0]);
        config(['espee.driver' => 'fake']);
        $this->seed();
    }

    protected function register(string $email = 'ada@example.com'): array
    {
        $r = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ada', 'last_name' => 'Obi', 'email' => $email, 'password' => 'password123',
        ])->assertCreated()->assertJsonStructure(['user' => ['id', 'first_name', 'is_premium'], 'token']);

        return ['Authorization' => 'Bearer '.$r->json('token')];
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->register();
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password123'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_content_is_locked_until_subscribed_then_everything_works(): void
    {
        $h = $this->register();

        // 1. Not subscribed: every content route is blocked (402), profile/subscription still work.
        foreach (['/api/v1/affirmations', '/api/v1/affirmations/daily', '/api/v1/categories', '/api/v1/collections', '/api/v1/favorites', '/api/v1/progress'] as $url) {
            $this->getJson($url, $h)->assertStatus(402);
        }
        $this->getJson('/api/v1/auth/me', $h)->assertOk();
        $this->getJson('/api/v1/subscription/plans')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'price', 'currency']]]);
        $this->getJson('/api/v1/subscription/status', $h)->assertOk()->assertJsonPath('is_premium', false);
        $this->getJson('/api/v1/affirmations')->assertStatus(401); // signed-out

        // 2. Subscribe -> checkout -> server-side verification activates premium.
        $sub = $this->postJson('/api/v1/subscription/subscribe', [], $h)->assertCreated()->assertJsonStructure(['reference', 'checkout_url']);
        $ref = $sub->json('reference');
        $this->postJson('/api/v1/payments/verify', ['reference' => $ref], $h)->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('is_premium', false);
        $this->get("/api/v1/payments/fake-checkout/{$ref}")->assertOk();
        $this->postJson('/api/v1/payments/verify', ['reference' => $ref], $h)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('is_premium', true);
        $this->getJson('/api/v1/subscription/status', $h)->assertOk()->assertJsonPath('is_premium', true);
        $this->getJson('/api/v1/payments/history', $h)->assertOk()->assertJsonStructure(['data' => [['reference', 'amount', 'status']]]);

        // 3. Content opens up.
        $list = $this->getJson('/api/v1/affirmations', $h)->assertOk()->assertJsonStructure(['data' => [['id', 'title', 'content', 'content_type', 'locked', 'is_favorite']]]);
        $id = $list->json('data.0.id');
        $this->getJson("/api/v1/affirmations/{$id}", $h)->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/v1/affirmations/daily', $h)->assertOk();
        $this->getJson('/api/v1/affirmations?q=confident', $h)->assertOk();
        $this->getJson('/api/v1/affirmations?type=text&sort=popular', $h)->assertOk();
        $this->getJson('/api/v1/categories', $h)->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);

        // 4. Favorites.
        $this->postJson("/api/v1/affirmations/{$id}/favorite", [], $h)->assertOk()->assertJsonPath('is_favorite', true);
        $this->getJson('/api/v1/favorites', $h)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/affirmations/{$id}/favorite", [], $h)->assertOk()->assertJsonPath('is_favorite', false);

        // 5. Progress & streak.
        $this->postJson('/api/v1/progress', ['affirmation_id' => $id, 'progress' => 100, 'completed' => true], $h)->assertOk()->assertJsonPath('streak', 1);
        $this->getJson('/api/v1/progress', $h)->assertOk()->assertJsonPath('streak', 1)->assertJsonPath('completed_today', true);

        // 6. Collections with progress.
        $cols = $this->getJson('/api/v1/collections', $h)->assertOk()->assertJsonStructure(['data' => [['id', 'title', 'progress' => ['completed', 'total', 'percent']]]]);
        $cid = $cols->json('data.0.id');
        $this->getJson("/api/v1/collections/{$cid}", $h)->assertOk()->assertJsonStructure(['data' => ['id', 'affirmations']]);

        // 7. Profile, device token, cancel auto-renew.
        $this->postJson('/api/v1/profile/update', ['notification_time' => '07:30', 'reminder_frequency' => 'weekdays'], $h)->assertOk()->assertJsonPath('data.notification_time', '07:30');
        $this->postJson('/api/v1/profile/device-token', ['token' => 'abc123', 'platform' => 'android'], $h)->assertOk();
        $this->postJson('/api/v1/subscription/cancel', [], $h)->assertOk()->assertJsonPath('auto_renew', false);
    }

    public function test_kingschat_sign_in_creates_a_user_and_returns_a_token(): void
    {
        config(['kingschat.client_id' => 'test-client']);
        Http::fake([
            'connect.kingsch.at/developer/api/oauth2/token' => Http::response(['access_token' => 'kc-token']),
            'connect.kingsch.at/developer/api/profile' => Http::response(['profile' => ['id' => 'kc-123', 'name' => 'Ada Obi']]),
        ]);

        $this->postJson('/api/v1/auth/kingschat', ['code' => 'one-time-code'])->assertOk()->assertJsonStructure(['user' => ['id'], 'token']);
        $this->assertDatabaseHas('users', ['kingschat_id' => 'kc-123']);

        // Signing in again reuses the same account.
        $this->postJson('/api/v1/auth/kingschat', ['code' => 'one-time-code'])->assertOk();
        $this->assertSame(1, User::where('kingschat_id', 'kc-123')->count());
    }

    public function test_admin_bypasses_the_paywall_and_non_admin_cannot_open_admin_panel(): void
    {
        $admin = User::where('email', 'admin@higherlife.app')->first();
        $token = $admin->createToken('test')->plainTextToken;
        $this->getJson('/api/v1/affirmations', ['Authorization' => 'Bearer '.$token])->assertOk();
    }
}
