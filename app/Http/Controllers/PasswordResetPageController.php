<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** The page behind the link in the reset email: choose a new password. */
class PasswordResetPageController extends Controller
{
    public function show(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'state' => 'form',
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
            'password.min' => 'Your password must be at least 8 characters.',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete(); // signs the account out everywhere
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->view('auth.reset-password', [
                'state' => 'expired',
                'token' => $data['token'],
                'email' => $data['email'],
            ], 422);
        }

        return view('auth.reset-password', ['state' => 'done', 'token' => '', 'email' => '']);
    }
}
