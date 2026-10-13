<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('price', 12, 2);
            $t->string('currency', 8)->default('ESP');
            $t->string('billing_interval')->default('monthly');
            $t->unsignedInteger('duration_days')->default(30);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('subscription_plans');
            $t->string('status')->default('pending')->index(); // active | expired | cancelled | pending
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->boolean('auto_renew')->default(true);
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference')->unique();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 8);
            $t->string('gateway');
            $t->string('status')->default('pending')->index(); // pending | success | failed
            $t->timestamp('paid_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('affirmations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title')->index();
            $t->text('description')->nullable();
            $t->text('content')->nullable();
            $t->string('content_type')->default('text')->index(); // text | audio | video
            $t->string('author')->nullable(); // author / narrator
            $t->string('thumbnail')->nullable();
            $t->boolean('is_premium')->default(false)->index();
            $t->string('status')->default('draft')->index(); // draft | published
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('affirmation_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('affirmation_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // audio | video
            $t->string('file_url');
            $t->unsignedInteger('duration')->nullable(); // seconds
            $t->unsignedBigInteger('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->timestamps();
        });

        Schema::create('collections', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('thumbnail')->nullable();
            $t->boolean('is_premium')->default(false);
            $t->string('status')->default('draft');
            $t->timestamps();
        });

        Schema::create('collection_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affirmation_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->unique(['collection_id', 'affirmation_id']);
        });

        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affirmation_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'affirmation_id']);
        });

        Schema::create('user_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affirmation_id')->constrained()->cascadeOnDelete();
            $t->string('content_type');
            $t->unsignedInteger('progress')->default(0); // percent 0-100
            $t->timestamp('completed_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->string('type')->default('announcement'); // daily | collection | new_content | premium | announcement
            $t->timestamp('scheduled_at')->nullable()->index();
            $t->string('status')->default('scheduled'); // scheduled | sent
            $t->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('notification_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->string('status')->default('sent');
            $t->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token')->unique();
            $t->string('platform')->default('android');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('daily_affirmations', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->foreignId('affirmation_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['daily_affirmations', 'device_tokens', 'notification_logs', 'notifications', 'user_progress', 'favorites', 'collection_items', 'collections', 'affirmation_media', 'affirmations', 'categories', 'transactions', 'subscriptions', 'subscription_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
