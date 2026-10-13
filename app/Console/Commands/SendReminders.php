<?php

namespace App\Console\Commands;

use App\Jobs\SendPushJob;
use App\Models\DailyAffirmation;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send daily affirmation reminders to users whose chosen time is now';

    public function handle(): int
    {
        $now = now();
        $q = User::where('notifications_enabled', true)->where('reminder_frequency', '!=', 'off')
            ->whereTime('notification_time', $now->format('H:i:00'));

        if ($now->isWeekend()) $q->where('reminder_frequency', '!=', 'weekdays');
        if (! $now->isMonday()) $q->where('reminder_frequency', '!=', 'weekly');

        $today = DailyAffirmation::whereDate('date', today())->with('affirmation')->first()?->affirmation;
        $title = 'Your daily affirmation ✨';
        $body = $today ? $today->title : 'Take a moment for yourself today.';

        $q->with(['deviceTokens' => fn ($t) => $t->where('is_active', true)])->chunkById(200, function ($users) use ($title, $body, $today) {
            foreach ($users as $user) {
                foreach ($user->deviceTokens as $d) {
                    SendPushJob::dispatch($d->token, $title, $body, ['type' => 'daily', 'affirmation_id' => $today?->id]);
                }
                if ($user->deviceTokens->isNotEmpty()) {
                    NotificationLog::create(['user_id' => $user->id, 'sent_at' => now(), 'status' => 'queued']);
                }
            }
        });

        return self::SUCCESS;
    }
}
