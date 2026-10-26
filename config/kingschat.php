<?php

return [
    // Your app's client id from the KingsChat developer console.
    'client_id' => env('KINGSCHAT_CLIENT_ID'),
    // Exchanges the one-time code returned by the KingsChat app for an access token.
    'token_url' => env('KINGSCHAT_TOKEN_URL', 'https://connect.kingsch.at/developer/api/oauth2/token'),
    // Optional. Returns the signed-in KingsChat user. If it fails, the user's details are read from the access token instead.
    'profile_url' => env('KINGSCHAT_PROFILE_URL', 'https://connect.kingsch.at/developer/api/user/profile'),
];
