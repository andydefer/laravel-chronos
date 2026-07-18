<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

/**
 * Validates the integrity of date and time ranges for an availability.
 *
 * Ensures that:
 * - Daily start time is before daily end time (cross-day is allowed)
 * - Validity start date is before validity end date
 * - Both validity start and end dates are provided (required for CREATE only)
 *
 * @example
 * $rule = new AvailabilityValidDateRangeRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle validation failure
 * }
 */
final class AvailabilityValidDateRangeRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Validates the integrity of daily time ranges and validity date ranges.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to Availability entity types during create or update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * {@inheritDoc}
     *
     * Validates the integrity of date and time ranges.
     *
     * @throws \RuntimeException If the record is not an AvailabilityRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        if (! $record instanceof AvailabilityRecord) {
            return null;
        }

        $dailyError = $this->validateDailyTimes($record->daily_start, $record->daily_end);

        if ($dailyError !== null) {
            return $dailyError;
        }

        return $this->validateValidityDates(
            $record->validity_start,
            $record->validity_end,
            $context->isCreate()
        );
    }

    /**
     * Validates daily start and end times.
     *
     * Cross-day (daily_start > daily_end) is allowed, but start and end times
     * cannot be equal (zero duration).
     *
     * @param  TimeZuluVO|null  $dailyStart  The daily start time
     * @param  TimeZuluVO|null  $dailyEnd  The daily end time
     * @return ValidationErrorRecord|null Error if validation fails, null otherwise
     */
    private function validateDailyTimes(
        ?TimeZuluVO $dailyStart,
        ?TimeZuluVO $dailyEnd
    ): ?ValidationErrorRecord {
        if ($dailyStart === null || $dailyEnd === null) {
            return null;
        }

        // Cross-day is allowed (daily_start > daily_end)
        // Only validate when not cross-day: start must be before end
        if ($dailyStart->isBefore($dailyEnd)) {
            return null;
        }

        // If not cross-day, they must be equal (invalid) or start > end (cross-day, valid)
        // So the only invalid case is when they are equal (zero duration)
        if ($dailyStart->isEqual($dailyEnd)) {
            return $this->createDailyTimeError($dailyStart, $dailyEnd);
        }

        // Cross-day (start > end) is valid
        return null;
    }

    /**
     * Validates validity start and end dates.
     *
     * For CREATE operations, both dates are required.
     * For UPDATE operations, dates are optional but if provided must be valid.
     *
     * @param  DateTimeZuluVO|null  $validityStart  The validity start date
     * @param  DateTimeZuluVO|null  $validityEnd  The validity end date
     * @param  bool  $isCreate  True if this is a CREATE operation
     * @return ValidationErrorRecord|null Error if validation fails, null otherwise
     */
    private function validateValidityDates(
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd,
        bool $isCreate
    ): ?ValidationErrorRecord {
        if ($isCreate) {
            if ($validityStart === null) {
                return $this->createMissingValidityDateError('start');
            }

            if ($validityEnd === null) {
                return $this->createMissingValidityDateError('end');
            }
        }

        if ($validityStart !== null && $validityEnd !== null) {
            if (! $validityStart->isBefore($validityEnd)) {
                return $this->createValidityDateRangeError($validityStart, $validityEnd);
            }
        }

        return null;
    }

    /**
     * Creates an error for invalid daily time range.
     *
     * @param  TimeZuluVO  $dailyStart  The daily start time
     * @param  TimeZuluVO  $dailyEnd  The daily end time
     * @return ValidationErrorRecord The error record
     */
    private function createDailyTimeError(
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Daily start time must be before daily end time.',
            context: Associative::from([
                'daily_start' => $dailyStart->toTimeString(),
                'daily_end' => $dailyEnd->toTimeString(),
            ])
        );
    }

    /**
     * Creates an error for missing validity date.
     *
     * @param  string  $type  The type of missing date ('start' or 'end')
     * @return ValidationErrorRecord The error record
     */
    private function createMissingValidityDateError(string $type): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf('Validity %s date is required for availability creation.', $type),
            context: Associative::from([
                'missing_field' => "validity_{$type}",
            ])
        );
    }

    /**
     * Creates an error for invalid validity date range.
     *
     * @param  DateTimeZuluVO  $validityStart  The validity start date
     * @param  DateTimeZuluVO  $validityEnd  The validity end date
     * @return ValidationErrorRecord The error record
     */
    private function createValidityDateRangeError(
        DateTimeZuluVO $validityStart,
        DateTimeZuluVO $validityEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Validity start date must be before validity end date.',
            context: Associative::from([
                'validity_start' => $validityStart->toDateString(),
                'validity_end' => $validityEnd->toDateString(),
            ])
        );
    }
}
