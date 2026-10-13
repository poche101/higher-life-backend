<?php

namespace App\Providers;

use App\Services\Payments\EspeeGateway;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\TransferGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('espee.driver')) {
            'transfer' => new TransferGateway(),
            'espee' => new EspeeGateway(),
            default => new FakeGateway(),
        });
    }

    public function boot(): void
    {
        // Signed checkout links must validate behind Nginx/Cloudflare: always build them with https in production.
        if (str_starts_with((string) config('app.url'), 'https://')) URL::forceScheme('https');

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    }
}
