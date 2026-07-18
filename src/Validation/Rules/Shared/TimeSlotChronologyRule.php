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
 *
 * @example
 * $rule = new TimeSlotChronologyRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle chronology error
 * }
 */
final class TimeSlotChronologyRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Validates that start datetime is before end datetime and duration is positive.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to Schedule and Impediment entity types during create or update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * {@inheritDoc}
     *
     * Validates the chronological integrity of the time slot.
     *
     * @throws \RuntimeException If the record is not a ScheduleRecord or ImpedimentRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $timeData = $this->extractTimeData($record);

        if ($timeData === null) {
            return null;
        }

        [$startDatetime, $endDatetime] = $timeData;

        if (! $startDatetime->isBefore($endDatetime)) {
            return $this->createChronologyError($startDatetime, $endDatetime);
        }

        if ($startDatetime->diffInMinutes($endDatetime) <= 0) {
            return $this->createZeroDurationError($startDatetime, $endDatetime);
        }

        return null;
    }

    /**
     * Extracts time data from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return array{DateTimeZuluVO, DateTimeZuluVO}|null Array of [start, end] or null
     */
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
     * Creates an error for invalid chronology.
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
     * Creates an error for zero duration.
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
