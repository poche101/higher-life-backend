<?php

namespace Database\Seeders;

use App\Models\Affirmation;
use App\Models\Category;
use App\Models\Collection;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::firstOrCreate(['name' => 'Higher Life Premium'], [
            'price' => 1, 'currency' => config('espee.currency', 'ESP'), 'billing_interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);

        // CHANGE THIS PASSWORD immediately after first login.
        User::firstOrCreate(['email' => 'admin@higherlife.app'], [
            'name' => 'Admin', 'first_name' => 'Admin', 'last_name' => 'Higher Life', 'password' => 'ChangeMe123!', 'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $cats = collect(['Confidence', 'Success', 'Focus', 'Motivation', 'Self-Love', 'Peace', 'Gratitude', 'Morning', 'Sleep', 'Discipline', 'Purpose', 'Relationships'])
            ->mapWithKeys(fn ($n) => [$n => Category::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n, 'is_active' => true])]);

        $samples = [
            ['Confidence', 'I am capable', 'I am capable of achieving everything I set my heart and mind to.', false],
            ['Confidence', 'I trust myself', 'I trust myself to make wise decisions and to grow from every experience.', false],
            ['Morning', 'A new day, a fresh start', 'Today is a gift. I welcome it with a grateful heart and an open mind.', false],
            ['Gratitude', 'Thankful for today', 'I am grateful for the blessings in my life, big and small.', false],
            ['Peace', 'Calm and centered', 'I breathe in peace and breathe out tension. I am calm and centered.', false],
            ['Self-Love', 'I am enough', 'I am worthy of love, joy and good things exactly as I am.', false],
            ['Focus', 'One step at a time', 'I give my full attention to what matters most, one step at a time.', true],
            ['Success', 'Success is my path', 'Every step I take moves me closer to the life I envision.', true],
            ['Discipline', 'Consistency wins', 'I show up for my goals every day, and small actions build lasting results.', true],
            ['Purpose', 'Living on purpose', 'My life has meaning, and I am walking boldly in my purpose.', true],
            ['Sleep', 'Rest well', 'I release today. My body and mind are ready to rest and renew.', true],
            ['Relationships', 'Open heart', 'I attract and nurture relationships built on love, trust and respect.', true],
        ];

        foreach ($samples as [$cat, $title, $text, $premium]) {
            Affirmation::firstOrCreate(['title' => $title], [
                'category_id' => $cats[$cat]->id, 'content' => $text, 'content_type' => 'text', 'is_premium' => $premium,
                'status' => 'published', 'published_at' => now(), 'author' => 'Higher Life',
            ]);
        }

        $journey = Collection::firstOrCreate(['title' => '7-Day Confidence Journey'], [
            'description' => 'A gentle week of affirmations to build unshakeable confidence.', 'is_premium' => false, 'status' => 'published',
        ]);
        if ($journey->affirmations()->count() === 0) {
            $journey->affirmations()->sync(Affirmation::where('content_type', 'text')->limit(7)->pluck('id')->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i + 1]])->all());
        }
    }
}
