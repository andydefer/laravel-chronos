<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Providers;

use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use Illuminate\Support\ServiceProvider;

final class LaravelChronosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repositories
        $this->app->singleton(
            AvailabilityRepository::class,
            fn ($app) => new AvailabilityRepository
        );

        $this->app->singleton(
            ScheduleRepository::class,
            fn ($app) => new ScheduleRepository
        );

        $this->app->singleton(
            ImpedimentRepository::class,
            fn ($app) => new ImpedimentRepository
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../aatabase/Migrations');
    }
}
