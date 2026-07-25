<?php

namespace App\Providers;

use App\Contracts\ShippingProvider;
use App\Enums\Shipping\Scenario;
use App\Services\Shipping\InMemoryProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShippingProvider::class, fn (): InMemoryProvider => new InMemoryProvider(Scenario::ImmediateSuccess));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
