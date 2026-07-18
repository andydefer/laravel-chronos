<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

/**
 * Validates that the availability duration meets the minimum required duration.
 *
 * Ensures that the time between daily_start and daily_end is at least
 * the configured minimum duration for availability.
 *
 * @example
 * $rule = new AvailabilityMinimumDurationRule($helper, $config);
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle validation failure
 * }
 */
final class AvailabilityMinimumDurationRule implements ValidationRule
{
    /**
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     * @param  ChronosConfigInterface  $config  Configuration containing minimum duration
     */
    public function __construct(
        private readonly ValidationHelperService $helper,
        private readonly ChronosConfigInterface $config
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Ensures availability duration meets the minimum required duration configured in the system.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule only applies to Availability entity types during create or update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * {@inheritDoc}
     *
     * Validates that the duration between daily_start and daily_end meets the minimum.
     *
     * @throws \RuntimeException If the record is not an AvailabilityRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        if (! $record instanceof AvailabilityRecord) {
            return null;
        }

        $dailyStart = $record->daily_start;
        $dailyEnd = $record->daily_end;

        if ($this->areTimesMissing($dailyStart, $dailyEnd)) {
            return null;
        }

        $minDuration = $this->config->getMinDuration(EntityType::AVAILABILITY);
        $actualDuration = $this->helper->getTimeDurationInMinutes($dailyStart, $dailyEnd);

        if ($actualDuration < $minDuration) {
            return $this->createDurationTooShortError(
                $minDuration,
                $actualDuration,
                $dailyStart,
                $dailyEnd
            );
        }

        return null;
    }

    /**
     * Checks if daily start or end times are missing.
     *
     * @param  TimeZuluVO|null  $dailyStart  The daily start time
     * @param  TimeZuluVO|null  $dailyEnd  The daily end time
     * @return bool True if either time is missing
     */
    private function areTimesMissing(?TimeZuluVO $dailyStart, ?TimeZuluVO $dailyEnd): bool
    {
        return $dailyStart === null || $dailyEnd === null;
    }

    /**
     * Creates an error for insufficient duration.
     *
     * @param  int  $minDuration  The minimum required duration in minutes
     * @param  int  $actualDuration  The actual duration in minutes
     * @param  TimeZuluVO  $dailyStart  The daily start time
     * @param  TimeZuluVO  $dailyEnd  The daily end time
     * @return ValidationErrorRecord The error record
     */
    private function createDurationTooShortError(
        int $minDuration,
        int $actualDuration,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability duration must be at least %d minutes. Current duration: %d minutes.',
                $minDuration,
                $actualDuration
            ),
            context: Associative::from([
                'min_duration' => $minDuration,
                'actual_duration' => $actualDuration,
                'daily_start' => $dailyStart->toTimeString(),
                'daily_end' => $dailyEnd->toTimeString(),
            ])
        );
    }
}
