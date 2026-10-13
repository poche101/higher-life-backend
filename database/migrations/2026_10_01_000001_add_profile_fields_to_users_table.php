<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('first_name')->nullable()->after('id');
            $t->string('last_name')->nullable()->after('first_name');
            $t->date('date_of_birth')->nullable();
            $t->string('avatar')->nullable();
            $t->string('role')->default('user')->index(); // user | admin
            $t->boolean('notifications_enabled')->default(true);
            $t->time('notification_time')->default('08:00:00');
            $t->string('reminder_frequency')->default('daily'); // daily | weekdays | weekly | off
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['first_name', 'last_name', 'date_of_birth', 'avatar', 'role', 'notifications_enabled', 'notification_time', 'reminder_frequency']);
        });
    }
};
