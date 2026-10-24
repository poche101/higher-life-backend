<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\FreshAuthPerRequest;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    use FreshAuthPerRequest;

    protected function setUp(): void
    {
        parent::setUp();
        config(['espee.driver' => 'fake']);
        $this->seed();
    }

    protected function member(): User
    {
        $this->postJson('/api/v1/auth/register', ['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada@example.com', 'password' => 'password123'])->assertCreated();

        return User::where('email', 'ada@example.com')->first();
    }

    public function test_forgot_password_sends_a_branded_email_with_a_link_to_our_page(): void
    {
        Notification::fake();
        $user = $this->member();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);
            $this->assertSame('Reset your Higher Life password', $mail->subject);
            $this->assertStringContainsString('/reset-password/', $mail->actionUrl);
            $this->assertStringContainsString('email=ada%40example.com', $mail->actionUrl);

            return true;
        });
    }

    public function test_unknown_email_gets_the_same_answer_and_nothing_is_sent(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonStructure(['message']);
        Notification::assertNothingSent();
    }

    public function test_reset_page_shows_the_form(): void
    {
        $this->get('/reset-password/sometoken?email=ada@example.com')
            ->assertOk()->assertSee('Choose a new password')->assertSee('ada@example.com');
    }

    public function test_full_reset_changes_the_password_and_signs_out_other_devices(): void
    {
        $user = $this->member();
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'ada@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertOk()->assertSee('Password updated');

        $this->assertCount(0, $user->fresh()->tokens);
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'brand-new-pass'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password123'])->assertStatus(422);
    }

    public function test_mismatched_or_short_passwords_are_refused(): void
    {
        $user = $this->member();
        $token = Password::createToken($user);

        $this->from('/reset-password/'.$token)->post('/reset-password', [
            'token' => $token, 'email' => 'ada@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'different',
        ])->assertRedirect('/reset-password/'.$token)->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'ada@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password123'])->assertOk();
    }

    public function test_a_wrong_or_used_link_shows_the_expired_page(): void
    {
        $user = $this->member();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => 'not-the-token', 'email' => 'ada@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422)->assertSee('This link has expired');

        $this->post('/reset-password', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'first-new-pass1', 'password_confirmation' => 'first-new-pass1'])->assertOk();
        // The same link cannot be used twice.
        $this->post('/reset-password', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'second-new-pass', 'password_confirmation' => 'second-new-pass'])
            ->assertStatus(422)->assertSee('This link has expired');
    }
}
