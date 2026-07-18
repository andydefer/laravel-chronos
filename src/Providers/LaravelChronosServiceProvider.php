<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Providers;

use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Repositories\AvailabilityRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Repositories\ImpedimentRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Repositories\ScheduleRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Observers\EnforceDomainMutationObserver;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use AndyDefer\LaravelChronos\Services\AvailabilityService;
use AndyDefer\LaravelChronos\Services\ImpedimentService;
use AndyDefer\LaravelChronos\Services\ScheduleService;
use AndyDefer\LaravelChronos\Services\SlotService;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityDaysFormatRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityMinimumDurationRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityNoOverlapRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityRequiredFieldsRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityValidDateRangeRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\CrossDayAvailabilityRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\DaysWithinValidityPeriodRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\NoFutureBookingsOnDeleteRule;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\SchedulableExistsRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\AvailabilityOwnershipValidationRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\BufferTimeRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\EntityOwnershipConsistencyRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\MaxDurationRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\NoTemporalConflictRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\TimeSlotChronologyRule;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\TimeSlotWithinAvailabilityRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\Validation\Validator;
use Illuminate\Support\ServiceProvider;

final class LaravelChronosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ============================================================
        // CONFIGURATION
        // ============================================================

        $this->mergeConfigFrom(
            __DIR__.'/../../config/chronos.php',
            'chronos'
        );

        $this->app->singleton(
            ChronosConfigInterface::class,
            ChronosConfig::class
        );

        // ============================================================
        // REPOSITORIES
        // ============================================================

        $this->app->singleton(
            AvailabilityRepositoryInterface::class,
            AvailabilityRepository::class
        );

        $this->app->singleton(
            ScheduleRepositoryInterface::class,
            ScheduleRepository::class
        );

        $this->app->singleton(
            ImpedimentRepositoryInterface::class,
            ImpedimentRepository::class
        );

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

        // ============================================================
        // SERVICES
        // ============================================================

        $this->app->singleton(
            AvailabilityServiceInterface::class,
            function ($app) {
                return new AvailabilityService(
                    $app->make(AvailabilityRepositoryInterface::class),
                    $app->make(ValidatorInterface::class)
                );
            }
        );

        $this->app->singleton(
            ScheduleServiceInterface::class,
            function ($app) {
                return new ScheduleService(
                    $app->make(ScheduleRepositoryInterface::class),
                    $app->make(ValidatorInterface::class)
                );
            }
        );

        $this->app->singleton(
            ImpedimentServiceInterface::class,
            function ($app) {
                return new ImpedimentService(
                    $app->make(ImpedimentRepositoryInterface::class),
                    $app->make(ValidatorInterface::class)
                );
            }
        );

        $this->app->singleton(
            SlotServiceInterface::class,
            function ($app) {
                return new SlotService(
                    $app->make(AvailabilityServiceInterface::class),
                    $app->make(ScheduleServiceInterface::class),
                    $app->make(ImpedimentServiceInterface::class)
                );
            }
        );

        $this->app->singleton(
            AvailabilityService::class,
            fn ($app) => $app->make(AvailabilityServiceInterface::class)
        );

        $this->app->singleton(
            ScheduleService::class,
            fn ($app) => $app->make(ScheduleServiceInterface::class)
        );

        $this->app->singleton(
            ImpedimentService::class,
            fn ($app) => $app->make(ImpedimentServiceInterface::class)
        );

        $this->app->singleton(
            SlotService::class,
            fn ($app) => $app->make(SlotServiceInterface::class)
        );

        // ============================================================
        // VALIDATION
        // ============================================================

        $this->app->singleton(
            ValidationHelperService::class,
            fn ($app) => new ValidationHelperService
        );

        // Bind ValidatorInterface to Validator
        $this->app->singleton(
            ValidatorInterface::class,
            function ($app) {
                $validator = new Validator;
                $helper = $app->make(ValidationHelperService::class);
                $config = $app->make(ChronosConfigInterface::class);

                // Register Availability rules
                $validator->addRules(EntityType::AVAILABILITY, [
                    new AvailabilityRequiredFieldsRule,
                    new AvailabilityDaysFormatRule,
                    new DaysWithinValidityPeriodRule($helper),
                    new AvailabilityNoOverlapRule($helper),
                    new AvailabilityMinimumDurationRule($helper, $config),
                    new AvailabilityValidDateRangeRule,
                    new NoFutureBookingsOnDeleteRule,
                    new CrossDayAvailabilityRule($helper),
                    new SchedulableExistsRule,
                ]);

                // Register Schedule & Impediment rules (shared)
                $sharedRules = [
                    new EntityOwnershipConsistencyRule,
                    new AvailabilityOwnershipValidationRule,
                    new TimeSlotWithinAvailabilityRule($helper),
                    new NoTemporalConflictRule,
                    new TimeSlotChronologyRule,
                    new BufferTimeRule($helper, $config),
                    new MaxDurationRule($helper, $config),
                ];

                $validator->addRules(EntityType::SCHEDULE, $sharedRules);
                $validator->addRules(EntityType::IMPEDIMENT, $sharedRules);

                return $validator;
            }
        );

        // Keep concrete Validator binding for backward compatibility
        $this->app->singleton(
            Validator::class,
            fn ($app) => $app->make(ValidatorInterface::class)
        );

        // ============================================================
        // MUTATION CONTEXT
        // ============================================================

        $this->app->singleton(
            ChronosMutationContext::class,
            fn ($app) => new ChronosMutationContext
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

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

    protected function registerModelObservers(): void
    {
        Availability::observe(EnforceDomainMutationObserver::class);
        Schedule::observe(EnforceDomainMutationObserver::class);
        Impediment::observe(EnforceDomainMutationObserver::class);
    }
}
