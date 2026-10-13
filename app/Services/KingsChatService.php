<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KingsChatService
{
    /**
     * Exchange the authorization code from the KingsChat app, then load the user's profile.
     * Everything happens server-side; the app never sees KingsChat tokens.
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
            throw new RuntimeException('KingsChat rejected the code (HTTP '.$token->status().').');
        }

        $access = $token->json('access_token') ?? $token->json('data.access_token');
        if (! $access) throw new RuntimeException('KingsChat returned no access token.');

        $res = Http::withToken($access)->acceptJson()->timeout(15)->get(config('kingschat.profile_url'));
        if (! $res->successful()) {
            throw new RuntimeException('KingsChat profile request failed (HTTP '.$res->status().').');
        }

        $j = $res->json();
        $u = data_get($j, 'profile') ?? data_get($j, 'data.profile') ?? data_get($j, 'data.user') ?? data_get($j, 'user') ?? data_get($j, 'data') ?? $j;

        $id = data_get($u, 'user_id') ?? data_get($u, 'id') ?? data_get($u, '_id') ?? data_get($u, 'username');
        if (! $id) throw new RuntimeException('KingsChat profile had no user id.');

        $email = data_get($u, 'email.address') ?? data_get($u, 'email');
        $name = data_get($u, 'name')
            ?? trim((string) data_get($u, 'first_name').' '.(string) data_get($u, 'last_name'))
            ?: (string) data_get($u, 'username', 'KingsChat user');

        return [
            'id' => (string) $id,
            'name' => (string) $name,
            'email' => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
            'avatar' => ($a = data_get($u, 'avatar_url') ?? data_get($u, 'avatar') ?? data_get($u, 'profile_image_url')) && is_string($a) ? $a : null,
        ];
    }
}
