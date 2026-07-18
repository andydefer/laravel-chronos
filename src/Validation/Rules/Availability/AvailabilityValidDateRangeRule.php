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
 * - Daily start time is before daily end time
 * - Validity start date is before validity end date
 * - Both validity start and end dates are provided
 */
final class AvailabilityValidDateRangeRule implements ValidationRule
{
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
     * Validate the integrity of date and time ranges.
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

        // Validate daily time ranges
        $dailyError = $this->validateDailyTimes($record->daily_start, $record->daily_end);
        if ($dailyError !== null) {
            return $dailyError;
        }

        // Validate validity date ranges
        $validityError = $this->validateValidityDates(
            $record->validity_start,
            $record->validity_end
        );
        if ($validityError !== null) {
            return $validityError;
        }

        return null;
    }

    /**
     * Validate daily start and end times.
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

        if ($this->isStartAfterEnd($dailyStart, $dailyEnd)) {
            return $this->createDailyTimeError($dailyStart, $dailyEnd);
        }

        return null;
    }

    /**
     * Check if start time is after or equal to end time.
     *
     * @param  TimeZuluVO  $start  The start time
     * @param  TimeZuluVO  $end  The end time
     * @return bool True if start is after or equal to end
     */
    private function isStartAfterEnd(TimeZuluVO $start, TimeZuluVO $end): bool
    {
        return ! $start->isBefore($end) && ! $start->isEqual($end);
    }

    /**
     * Create an error for invalid daily time range.
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
     * Validate validity start and end dates.
     *
     * @param  DateTimeZuluVO|null  $validityStart  The validity start date
     * @param  DateTimeZuluVO|null  $validityEnd  The validity end date
     * @return ValidationErrorRecord|null Error if validation fails, null otherwise
     */
    private function validateValidityDates(
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): ?ValidationErrorRecord {
        // Check if both dates are provided
        if ($validityStart === null) {
            return $this->createMissingValidityDateError('start');
        }

        if ($validityEnd === null) {
            return $this->createMissingValidityDateError('end');
        }

        // Check if start is before end
        if ($this->isValidityStartAfterEnd($validityStart, $validityEnd)) {
            return $this->createValidityDateRangeError($validityStart, $validityEnd);
        }

        return null;
    }

    /**
     * Check if validity start is after or equal to validity end.
     *
     * @param  DateTimeZuluVO  $start  The start date
     * @param  DateTimeZuluVO  $end  The end date
     * @return bool True if start is after or equal to end
     */
    private function isValidityStartAfterEnd(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool {
        return ! $start->isBefore($end);
    }

    /**
     * Create an error for missing validity date.
     *
     * @param  string  $type  The type of missing date ('start' or 'end')
     * @return ValidationErrorRecord The error record
     */
    private function createMissingValidityDateError(string $type): ValidationErrorRecord
    {
        $message = sprintf(
            'Validity %s date is required for availability.',
            $type
        );

        return new ValidationErrorRecord(
            rule: self::class,
            message: $message,
            context: Associative::from([
                'missing_field' => "validity_{$type}",
            ])
        );
    }

    /**
     * Create an error for invalid validity date range.
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
