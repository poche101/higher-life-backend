<?php

namespace App\Console\Commands;

use App\Models\Affirmation;
use App\Models\DailyAffirmation;
use Illuminate\Console\Command;

class PublishDailyAffirmation extends Command
{
    protected $signature = 'affirmations:publish-daily';
    protected $description = "Pick today's free daily affirmation (avoids repeats from the last 30 days)";

    public function handle(): int
    {
        if (DailyAffirmation::whereDate('date', today())->exists()) {
            $this->info('Already set for today.');
            return self::SUCCESS;
        }

        $recent = DailyAffirmation::where('date', '>=', today()->subDays(30))->pluck('affirmation_id');

        $pick = Affirmation::published()->where('is_premium', false)->where('content_type', 'text')
            ->whereNotIn('id', $recent)->inRandomOrder()->first()
            ?? Affirmation::published()->where('is_premium', false)->inRandomOrder()->first();

        if (! $pick) {
            $this->warn('No published free affirmations available.');
            return self::FAILURE;
        }

        DailyAffirmation::create(['date' => today(), 'affirmation_id' => $pick->id]);
        $this->info("Today's affirmation: {$pick->title}");
        return self::SUCCESS;
    }
}
