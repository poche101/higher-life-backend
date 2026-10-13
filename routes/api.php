<?php

use App\Http\Controllers\Api\AffirmationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Auth (public) ----
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('kingschat', [AuthController::class, 'kingschat']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // ---- Public: only what's needed to sign up and see the subscription offer ----
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);

    // Payment provider -> Laravel (signature verified inside controller)
    Route::post('payments/webhook', [PaymentController::class, 'webhook']);
    Route::get('payments/fake-checkout/{reference}', [PaymentController::class, 'fakeCheckout'])->name('payments.fake-checkout');

    // ---- Authenticated ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::post('profile/update', [ProfileController::class, 'update']);
        Route::delete('profile', [ProfileController::class, 'destroy']);
        Route::post('profile/device-token', [DeviceTokenController::class, 'store']);

        // ---- Subscribers only: every affirmation, collection, favorite and progress route (402 if not subscribed) ----
        Route::middleware('premium')->group(function () {
            Route::get('categories', [CategoryController::class, 'index']);
            Route::get('categories/{category}/affirmations', [CategoryController::class, 'affirmations']);
            Route::get('affirmations', [AffirmationController::class, 'index']);
            Route::get('affirmations/daily', [AffirmationController::class, 'daily']);
            Route::get('affirmations/{affirmation}', [AffirmationController::class, 'show']);
            Route::post('affirmations/{affirmation}/favorite', [AffirmationController::class, 'toggleFavorite']);
            Route::get('collections', [CollectionController::class, 'index']);
            Route::get('collections/{collection}', [CollectionController::class, 'show']);
            Route::post('collections/{collection}/progress', [CollectionController::class, 'progress']);
            Route::get('favorites', [FavoriteController::class, 'index']);
            Route::get('progress', [ProgressController::class, 'index']);
            Route::post('progress', [ProgressController::class, 'store']);
        });

        Route::get('subscription', [SubscriptionController::class, 'show']);
        Route::get('subscription/status', [SubscriptionController::class, 'status']);
        Route::post('subscription/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);

        Route::post('payments/initiate', [PaymentController::class, 'initiate']);
        Route::post('payments/verify', [PaymentController::class, 'verify']);
        Route::post('payments/submit', [PaymentController::class, 'submit'])->middleware('throttle:10,1');
        Route::get('payments/history', [PaymentController::class, 'history']);
    });
});
