<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            // Espees' payment_ref. Unique, so one Espees payment can never activate two subscriptions.
            $t->string('gateway_ref')->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropUnique(['gateway_ref']);
            $t->dropColumn('gateway_ref');
        });
    }
};
