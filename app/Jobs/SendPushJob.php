<?php

namespace App\Jobs;

use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $title, public string $body, public array $data = []) {}

    public function handle(PushService $push): void
    {
        $push->send($this->token, $this->title, $this->body, $this->data);
    }
}
