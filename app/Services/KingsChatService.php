<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KingsChatService
{
    /**
     * Exchange the one-time authorization code from the KingsChat app for tokens, then work out who signed in.
     * Everything happens server-side; the app never sees KingsChat tokens.
     *
     * Token exchange (KingsChat docs): POST {token_url} with JSON {grant_type: "code", client_id, code}, no auth header.
     * The reply holds access_token (a JWT), refresh_token and expires_in_millis. The user's details are read from the
     * profile endpoint when it answers, and from the access token's own claims otherwise.
     *
     * @return array{id: string, name: string, email: ?string, avatar: ?string}
     */
    public function profileFromCode(string $code): array
    {
        $token = Http::acceptJson()->asJson()->timeout(15)->post(config('kingschat.token_url'), [
            'grant_type' => 'code',
            'client_id' => config('kingschat.client_id'),
            'code' => $code,
        ]);

        if (! $token->successful()) {
            // KingsChat answers with {"code": "invalid_client|invalid_code|invalid_metadata|...", "user_message": "..."}.
            throw new RuntimeException('KingsChat rejected the code (HTTP '.$token->status().', '.($token->json('code') ?? 'no code').': '.($token->json('user_message') ?? 'no message').').');
        }

        $access = $token->json('access_token') ?? $token->json('data.access_token');
        if (! $access) throw new RuntimeException('KingsChat returned no access token.');

        $claims = $this->claims((string) $access);

        // Profile details are a bonus: if that endpoint is unavailable we still sign the user in from the token's claims.
        $u = [];
        if ($profileUrl = config('kingschat.profile_url')) {
            try {
                $res = Http::withToken($access)->acceptJson()->timeout(15)->get($profileUrl);
                if ($res->successful()) {
                    $j = $res->json();
                    $u = (array) (data_get($j, 'profile') ?? data_get($j, 'data.profile') ?? data_get($j, 'data.user') ?? data_get($j, 'user') ?? data_get($j, 'data') ?? $j);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => data_get($u, $k) ?? data_get($claims, $k))->first(fn ($v) => filled($v) && is_scalar($v));

        $id = $pick(['user_id', 'id', '_id', 'sub', 'username']);
        if (! $id) throw new RuntimeException('KingsChat did not tell us who signed in (no user id in the profile or the token).');

        $email = $pick(['email.address', 'email']);
        $first = $pick(['first_name', 'given_name']);
        $last = $pick(['last_name', 'family_name']);
        $name = $pick(['name', 'display_name']) ?? trim($first.' '.$last) ?: (string) ($pick(['username']) ?? 'KingsChat user');
        $avatar = $pick(['avatar_url', 'avatar', 'profile_image_url', 'picture']);

        return [
            'id' => (string) $id,
            'name' => (string) $name,
            'email' => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
            'avatar' => is_string($avatar) ? $avatar : null,
        ];
    }

    /** Payload of a JWT. The token came straight from KingsChat over HTTPS in answer to our own request, so it is read, not re-verified. */
    private function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return [];

        $json = base64_decode(strtr($parts[1], '-_', '+/').str_repeat('=', (4 - strlen($parts[1]) % 4) % 4), true);
        $data = $json ? json_decode($json, true) : null;

        return is_array($data) ? $data : [];
    }
}
