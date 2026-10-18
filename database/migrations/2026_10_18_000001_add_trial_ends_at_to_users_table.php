<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('trial_ends_at')->nullable()->after('role');
        });

        // Existing accounts that have never subscribed also get the free trial, counted from today.
        $days = (int) config('higherlife.trial_days', 7);
        if ($days > 0) {
            DB::table('users')
                ->whereNull('trial_ends_at')
                ->whereNotIn('id', DB::table('subscriptions')->whereIn('status', ['active', 'expired'])->select('user_id'))
                ->update(['trial_ends_at' => now()->addDays($days)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('trial_ends_at');
        });
    }
};
