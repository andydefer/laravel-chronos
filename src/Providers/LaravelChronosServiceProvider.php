<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Providers;

use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Observers\EnforceDomainMutationObserver;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Validation\Validator;
use Illuminate\Support\ServiceProvider;

final class LaravelChronosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/chronos.php',
            'chronos'
        );

        $this->app->singleton(
            ChronosConfigInterface::class,
            ChronosConfig::class
        );

        // Repositories
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

        // Validator
        $this->app->singleton(Validator::class, function ($app) {
            return new Validator;
        });

        // Mutation Context
        $this->app->singleton(
            ChronosMutationContext::class,
            fn ($app) => new ChronosMutationContext
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Register model observers to enforce domain mutation rules
        $this->registerModelObservers();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/chronos.php' => config_path('chronos.php'),
            ], 'chronos-config');

            $this->publishes([
                __DIR__.'/../../database/Migrations' => database_path('migrations'),
            ], 'chronos-migrations');

            $this->publishes([
                __DIR__.'/../../config/chronos.php' => config_path('chronos.php'),
                __DIR__.'/../../database/Migrations' => database_path('migrations'),
            ], 'chronos-all');
        }
    }

    /**
     * Register observers for domain models to enforce business rules.
     * Ensures data integrity and domain constraints during model operations.
     */
    protected function registerModelObservers(): void
    {
        Availability::observe(EnforceDomainMutationObserver::class);
        Schedule::observe(EnforceDomainMutationObserver::class);
        Impediment::observe(EnforceDomainMutationObserver::class);
    }
}
