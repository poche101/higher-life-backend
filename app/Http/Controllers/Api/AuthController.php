<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\KingsChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', PasswordRule::min(8)],
            'date_of_birth' => 'nullable|date|before:today',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = User::create([
            'name' => $data['first_name'].' '.$data['last_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'avatar' => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', config('espee.media_disk')) : null,
        ]);

        // $user->sendEmailVerificationNotification(); // enable once User implements MustVerifyEmail + mail is configured

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->input('device_name', 'mobile'))->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'The email or password is incorrect.', 'errors' => ['email' => ['The email or password is incorrect.']]], 422);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->input('device_name', 'mobile'))->plainTextToken,
        ]);
    }

    /** "Continue with KingsChat": the app sends the one-time code from the KingsChat app; we verify it server-side. */
    public function kingschat(Request $request, KingsChatService $kc)
    {
        $data = $request->validate(['code' => 'required|string|max:1000']);

        try {
            $p = $kc->profileFromCode($data['code']);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'We couldn\'t verify your KingsChat sign-in. Please try again.'], 422);
        }

        $user = User::where('kingschat_id', $p['id'])->first();

        if (! $user) {
            // Never auto-link to an existing email account (avoids account takeover); use a placeholder if the email is taken.
            $email = $p['email'] && ! User::where('email', $p['email'])->exists()
                ? $p['email']
                : 'kc_'.preg_replace('/[^A-Za-z0-9]/', '', $p['id']).'@kingschat.invalid';
            [$first, $last] = array_pad(explode(' ', $p['name'], 2), 2, '');

            $user = User::create([
                'name' => $p['name'], 'first_name' => $first, 'last_name' => $last,
                'email' => $email, 'password' => Str::random(40), 'kingschat_id' => $p['id'],
                'avatar' => $p['avatar'], 'email_verified_at' => now(),
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->input('device_name', 'mobile'))->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out. See you soon!']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            // KingsChat accounts without a real email carry a placeholder address; there is nothing to send to.
            if (! str_ends_with(strtolower($request->input('email')), '@kingschat.invalid')) {
                Password::sendResetLink($request->only('email'));
            }
        } catch (\Throwable $e) {
            // Usually the mail server settings. Log it for you, but never reveal to the caller whether an account exists.
            report($e);
        }

        return response()->json(['message' => 'If that email is registered, we have sent a link to reset your password. It can take a minute, and may land in spam.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required', 'email' => 'required|email', 'password' => ['required', PasswordRule::min(8)],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Your password has been reset.'])
            : response()->json(['message' => __($status)], 422);
    }
}
