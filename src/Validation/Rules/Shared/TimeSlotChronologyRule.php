<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Shared;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Validates the chronological integrity of time slots.
 *
 * Ensures that:
 * - Start datetime is before end datetime
 * - Duration is greater than 0 minutes
 */
final class TimeSlotChronologyRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that start datetime is before end datetime and duration is positive.';
    }

    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * Validate the chronological integrity of the time slot.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract time slot data from the record
        $timeData = $this->extractTimeData($record);

        if ($timeData === null) {
            return null;
        }

        [$startDatetime, $endDatetime] = $timeData;

        // Validate start is before end
        if (! $this->isStartBeforeEnd($startDatetime, $endDatetime)) {
            return $this->createChronologyError($startDatetime, $endDatetime);
        }

        // Validate duration is greater than 0
        if (! $this->hasPositiveDuration($startDatetime, $endDatetime)) {
            return $this->createZeroDurationError($startDatetime, $endDatetime);
        }

        return null;
    }

    /**
     * Extract time data from the record.
     *
     * @param mixed $record The record to extract from
     * @return array{DateTimeZuluVO, DateTimeZuluVO}|null Array of [start, end] or null     */
    private function extractTimeData(mixed $record): ?array
    {
        $startDatetime = null;
        $endDatetime = null;

        if ($record instanceof ScheduleRecord) {
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        } elseif ($record instanceof ImpedimentRecord) {
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        }

        if ($startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$startDatetime, $endDatetime];
    }

    /**
     * Check if start is before end.
     *
     * @param  DateTimeZuluVO  $start  The start datetime
     * @param  DateTimeZuluVO  $end  The end datetime
     * @return bool True if start is before end
     */
    private function isStartBeforeEnd(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        return $start->isBefore($end);
    }

    /**
     * Check if duration is positive.
     *
     * @param  DateTimeZuluVO  $start  The start datetime
     * @param  DateTimeZuluVO  $end  The end datetime
     * @return bool True if duration > 0
     */
    private function hasPositiveDuration(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        return $start->diffInMinutes($end) > 0;
    }

    /**
     * Create an error for invalid chronology.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @return ValidationErrorRecord The error record
     */
    private function createChronologyError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Start datetime must be before end datetime.',
            context: Associative::from([
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
            ])
        );
    }

    /**
     * Create an error for zero duration.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @return ValidationErrorRecord The error record
     */
    private function createZeroDurationError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Duration must be greater than 0 minutes.',
            context: Associative::from([
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
            ])
        );
    }
}
