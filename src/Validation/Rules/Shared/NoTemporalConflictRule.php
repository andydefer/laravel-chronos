<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Shared;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Prevents temporal conflicts between schedules and impediments.
 *
 * Ensures that no two schedules or impediments overlap in time on the same
 * availability, maintaining the integrity of the schedule. This prevents
 * double-booking and resource conflicts.
 *
 * @example
 * $rule = new NoTemporalConflictRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle temporal conflict
 * }
 */
final class NoTemporalConflictRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Prevents temporal conflicts between schedules and impediments on the same availability.';
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
     * Validates that there are no temporal conflicts.
     *
     * @throws \RuntimeException If the record is not a ScheduleRecord or ImpedimentRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $conflictData = $this->extractConflictData($record, $context);

        if ($conflictData === null) {
            return null;
        }

        [$availabilityId, $startDatetime, $endDatetime, $excludeId] = $conflictData;

        $scheduleConflict = $this->findConflictingSchedule(
            $availabilityId,
            $startDatetime,
            $endDatetime,
            $excludeId
        );

        if ($scheduleConflict !== null) {
            return $this->createConflictError('schedule', $scheduleConflict, $startDatetime, $endDatetime);
        }

        $impedimentConflict = $this->findConflictingImpediment(
            $availabilityId,
            $startDatetime,
            $endDatetime,
            $excludeId
        );

        if ($impedimentConflict !== null) {
            return $this->createConflictError('impediment', $impedimentConflict, $startDatetime, $endDatetime);
        }

        return null;
    }

    /**
     * Extracts conflict data from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @param  ValidationContext  $context  The validation context
     * @return array{int, DateTimeZuluVO, DateTimeZuluVO, int|null}|null
     */
    private function extractConflictData(mixed $record, ValidationContext $context): ?array
    {
        $availabilityId = null;
        $startDatetime = null;
        $endDatetime = null;
        $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;

        if ($record instanceof ScheduleRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        } elseif ($record instanceof ImpedimentRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        }

        if ($availabilityId === null || $startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$availabilityId, $startDatetime, $endDatetime, $excludeId];
    }

    /**
     * Finds a conflicting schedule.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  int|null  $excludeId  The ID to exclude
     * @return Schedule|null The conflicting schedule or null
     */
    private function findConflictingSchedule(
        int $availabilityId,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        ?int $excludeId
    ): ?Schedule {
        $query = Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $endDatetime->toDateTimeString())
            ->where('end_datetime', '>', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Finds a conflicting impediment.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  int|null  $excludeId  The ID to exclude
     * @return Impediment|null The conflicting impediment or null
     */
    private function findConflictingImpediment(
        int $availabilityId,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        ?int $excludeId
    ): ?Impediment {
        $query = Impediment::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $endDatetime->toDateTimeString())
            ->where('end_datetime', '>', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Creates an error for conflict.
     *
     * @param  string  $type  The event type ('schedule' or 'impediment')
     * @param  Schedule|Impediment  $conflict  The conflicting event
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @return ValidationErrorRecord The error record
     */
    private function createConflictError(
        string $type,
        Schedule|Impediment $conflict,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime
    ): ValidationErrorRecord {
        $contextKey = $type === 'schedule' ? 'conflicting_schedule_id' : 'conflicting_impediment_id';

        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Time slot %s to %s conflicts with existing %s #%d (%s to %s).',
                $startDatetime->toDateTimeString(),
                $endDatetime->toDateTimeString(),
                $type,
                $conflict->id,
                $conflict->start_datetime->format('Y-m-d H:i:s'),
                $conflict->end_datetime->format('Y-m-d H:i:s')
            ),
            context: Associative::from([
                $contextKey => $conflict->id,
                'conflicting_start' => $conflict->start_datetime->format('Y-m-d H:i:s'),
                'conflicting_end' => $conflict->end_datetime->format('Y-m-d H:i:s'),
            ])
        );
    }
}
