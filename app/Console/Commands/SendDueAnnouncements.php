<?php

namespace App\Console\Commands;

use App\Jobs\SendPushJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use Illuminate\Console\Command;

class SendDueAnnouncements extends Command
{
    protected $signature = 'announcements:send-due';
    protected $description = 'Send admin-scheduled announcements that are due';

    public function handle(): int
    {
        AppNotification::where('status', 'scheduled')->where('scheduled_at', '<=', now())->each(function ($n) {
            $tokens = DeviceToken::where('is_active', true)->with('user');

            if ($n->type === 'premium') {
                $tokens->whereHas('user.subscriptions', fn ($s) => $s->where('status', 'active')->where('expires_at', '>', now()));
            }

            $tokens->chunkById(500, function ($chunk) use ($n) {
                foreach ($chunk as $d) {
                    SendPushJob::dispatch($d->token, $n->title, $n->body, ['type' => $n->type]);
                    NotificationLog::create(['user_id' => $d->user_id, 'notification_id' => $n->id, 'sent_at' => now(), 'status' => 'queued']);
                }
            });

            $n->update(['status' => 'sent']);
        });

        return self::SUCCESS;
    }
}
