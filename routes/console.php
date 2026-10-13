<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('affirmations:publish-daily')->dailyAt('00:05');
Schedule::command('subscriptions:expire')->hourly();
Schedule::command('reminders:send')->everyMinute();
Schedule::command('announcements:send-due')->everyMinute();
Schedule::command('payments:reverify')->everyTwoMinutes()->withoutOverlapping();
