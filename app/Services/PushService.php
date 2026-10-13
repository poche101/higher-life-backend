<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PushService
{
    /**
     * Send one push notification.
     * TODO: connect Firebase Cloud Messaging, e.g. `composer require kreait/laravel-firebase`,
     * then send a CloudMessage to $token. Until then notifications are written to the log.
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        Log::info('[push] '.$title.' -> '.substr($token, 0, 12).'…', ['body' => $body, 'data' => $data]);
        return true;
    }
}
