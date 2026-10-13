<?php

use App\Http\Controllers\PaymentReturnController;
use App\Livewire\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// "Pay with Espees" button page (opened by the app with a signed link), then the Espees Merchant dashboard URLs.
Route::get('pay/{reference}', [PaymentReturnController::class, 'checkout'])->middleware(['signed', 'throttle:30,1'])->name('payment.checkout');
// Espees Merchant dashboard URLs. They only DISPLAY a verified status; they never activate anything on their own.
Route::get('payment/success', [PaymentReturnController::class, 'success'])->middleware('throttle:30,1')->name('payment.success');
Route::get('payment/failed', [PaymentReturnController::class, 'failed'])->middleware('throttle:30,1')->name('payment.failed');

Route::prefix('admin')->group(function () {
    Route::get('login', Admin\Login::class)->name('login')->middleware('guest');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', Admin\Dashboard::class)->name('admin.dashboard');
        Route::get('affirmations', Admin\Affirmations::class)->name('admin.affirmations');
        Route::get('categories', Admin\Categories::class)->name('admin.categories');
        Route::get('collections', Admin\Collections::class)->name('admin.collections');
        Route::get('users', Admin\Users::class)->name('admin.users');
        Route::get('billing', Admin\Billing::class)->name('admin.billing');
        Route::get('notifications', Admin\Notifications::class)->name('admin.notifications');
        Route::post('logout', function () {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('login');
        })->name('admin.logout');
    });
});
