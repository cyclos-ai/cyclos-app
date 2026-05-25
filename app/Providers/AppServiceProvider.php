<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Tracking\ContainerTrackingServiceInterface::class,
            \App\Services\Tracking\ContainerTrackingService::class
        );

        $this->app->bind(
            \App\Services\Demurrage\DemurrageCalculatorInterface::class,
            \App\Services\Demurrage\DemurrageCalculator::class
        );
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard();
    }
}
