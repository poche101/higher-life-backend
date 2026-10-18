<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // TEXT holds 65,535 bytes, which long affirmations with accents or emoji could exceed. MEDIUMTEXT holds 16 MB.
    public function up(): void
    {
        Schema::table('affirmations', function (Blueprint $t) {
            $t->mediumText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('affirmations', function (Blueprint $t) {
            $t->text('content')->nullable()->change();
        });
    }
};
