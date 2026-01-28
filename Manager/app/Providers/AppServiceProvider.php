<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ScheduleCrypto;
use App\Observers\ScheduleCryptoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ScheduleCrypto::observe(ScheduleCryptoObserver::class);
    }
}
