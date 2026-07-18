<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

/**
 * Validates cross-day availability configurations.
 *
 * Ensures that when an availability crosses midnight (daily_start > daily_end),
 * the days array has at least 2 consecutive days to cover both the start and end days.
 */
final class CrossDayAvailabilityRule implements ValidationRule
{
    /**
     * Constructor with dependency injection.
     *
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     */
    public function __construct(
        private readonly ValidationHelperService $helper
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that cross-day availabilities have at least 2 consecutive days to cover both start and end days.';
    }

    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * Validate cross-day availability configuration.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        if (! $record instanceof AvailabilityRecord) {
            return null;
        }

        // Skip validation if required data is missing (handled by other rules)
        if ($this->isRequiredDataMissing($record)) {
            return null;
        }

        // Check if this is a cross-day availability
        if (! $this->isCrossDayAvailability($record->daily_start, $record->daily_end)) {
            return null;
        }

        // Validate that days are consecutive and at least 2
        $dayStrings = $record->days->toStrings();

        if (! $this->areDaysValidForCrossDay($dayStrings)) {
            return $this->createNonConsecutiveDaysError($dayStrings, $record);
        }

        return null;
    }

    /**
     * Check if required data is missing for validation.
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @return bool True if required data is missing
     */
    private function isRequiredDataMissing(AvailabilityRecord $record): bool
    {
        return $record->daily_start === null
            || $record->daily_end === null
            || $record->days === null
            || $record->days->isEmpty();
    }

    /**
     * Check if the availability crosses midnight.
     *
     * @param  TimeZuluVO|null  $dailyStart  The daily start time
     * @param  TimeZuluVO|null  $dailyEnd  The daily end time
     * @return bool True if start is after end (crosses midnight)
     */
    private function isCrossDayAvailability(
        ?TimeZuluVO $dailyStart,
        ?TimeZuluVO $dailyEnd
    ): bool {
        return $dailyStart !== null
            && $dailyEnd !== null
            && $dailyStart->isAfter($dailyEnd);
    }

    /**
     * Check if days are valid for a cross-day availability.
     * Must have at least 2 consecutive days.
     *
     * @param  array<string>  $dayStrings  The day strings to check
     * @return bool True if valid (at least 2 consecutive days)
     */
    private function areDaysValidForCrossDay(array $dayStrings): bool
    {
        // Cross-day availability needs at least 2 days
        if (count($dayStrings) < 2) {
            return false;
        }

        return WeekDay::areConsecutive($dayStrings);
    }

    /**
     * Create an error for non-consecutive days in cross-day availability.
     *
     * @param  array<string>  $dayStrings  The day strings provided
     * @param  AvailabilityRecord  $record  The record being validated
     * @return ValidationErrorRecord The error record
     */
    private function createNonConsecutiveDaysError(
        array $dayStrings,
        AvailabilityRecord $record
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability crosses midnight but days array is not consecutive. Days: %s',
                implode(', ', $dayStrings)
            ),
            context: Associative::from([
                'days' => $dayStrings,
                'daily_start' => $record->daily_start->toTimeString(),
                'daily_end' => $record->daily_end->toTimeString(),
            ])
        );
    }
}
