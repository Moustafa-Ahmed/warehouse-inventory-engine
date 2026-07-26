<?php

namespace App\Providers;

use App\Contracts\ShippingProvider;
use App\Services\Shipping\PersistentMockProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShippingProvider::class, PersistentMockProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'shipping-provider-webhooks',
            fn (Request $request): Limit => Limit::perMinute(
                (int) config('shipping.webhook.rate_limit_per_minute'),
            )->by(
                (string) $request->header('X-Shipping-Provider', 'unknown')
                .'|'.$request->ip(),
            ),
        );
    }
}
