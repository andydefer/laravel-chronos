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
 * availability, maintaining the integrity of the schedule.
 */
final class NoTemporalConflictRule implements ValidationRule
{
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
     * Validate that there are no temporal conflicts.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract conflict data from the record
        $conflictData = $this->extractConflictData($record, $context);

        if ($conflictData === null) {
            return null;
        }

        [$availabilityId, $startDatetime, $endDatetime, $excludeId] = $conflictData;

        // Check for conflicting schedules
        $scheduleConflict = $this->findConflictingSchedule(
            $availabilityId,
            $startDatetime,
            $endDatetime,
            $excludeId
        );

        if ($scheduleConflict !== null) {
            return $this->createScheduleConflictError(
                $startDatetime,
                $endDatetime,
                $scheduleConflict
            );
        }

        // Check for conflicting impediments
        $impedimentConflict = $this->findConflictingImpediment(
            $availabilityId,
            $startDatetime,
            $endDatetime,
            $excludeId
        );

        if ($impedimentConflict !== null) {
            return $this->createImpedimentConflictError(
                $startDatetime,
                $endDatetime,
                $impedimentConflict
            );
        }

        return null;
    }

    /**
     * Extract conflict data from the record.
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
        $excludeId = null;

        if ($record instanceof ScheduleRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
            $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;
        } elseif ($record instanceof ImpedimentRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
            $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;
        }

        if ($availabilityId === null || $startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$availabilityId, $startDatetime, $endDatetime, $excludeId];
    }

    /**
     * Find a conflicting schedule.
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
            ->where(function ($q) use ($startDatetime, $endDatetime) {
                $q->where('start_datetime', '<', $endDatetime->toDateTimeString())
                    ->where('end_datetime', '>', $startDatetime->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Find a conflicting impediment.
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
            ->where(function ($q) use ($startDatetime, $endDatetime) {
                $q->where('start_datetime', '<', $endDatetime->toDateTimeString())
                    ->where('end_datetime', '>', $startDatetime->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Create an error for schedule conflict.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  Schedule  $conflict  The conflicting schedule
     * @return ValidationErrorRecord The error record
     */
    private function createScheduleConflictError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        Schedule $conflict
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Time slot %s to %s conflicts with existing schedule #%d (%s to %s).',
                $startDatetime->toDateTimeString(),
                $endDatetime->toDateTimeString(),
                $conflict->id,
                $conflict->start_datetime->format('Y-m-d H:i:s'),
                $conflict->end_datetime->format('Y-m-d H:i:s')
            ),
            context: Associative::from([
                'conflicting_schedule_id' => $conflict->id,
                'conflicting_start' => $conflict->start_datetime->format('Y-m-d H:i:s'),
                'conflicting_end' => $conflict->end_datetime->format('Y-m-d H:i:s'),
            ])
        );
    }

    /**
     * Create an error for impediment conflict.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  Impediment  $conflict  The conflicting impediment
     * @return ValidationErrorRecord The error record
     */
    private function createImpedimentConflictError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        Impediment $conflict
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Time slot %s to %s conflicts with existing impediment #%d (%s to %s).',
                $startDatetime->toDateTimeString(),
                $endDatetime->toDateTimeString(),
                $conflict->id,
                $conflict->start_datetime->format('Y-m-d H:i:s'),
                $conflict->end_datetime->format('Y-m-d H:i:s')
            ),
            context: Associative::from([
                'conflicting_impediment_id' => $conflict->id,
                'conflicting_start' => $conflict->start_datetime->format('Y-m-d H:i:s'),
                'conflicting_end' => $conflict->end_datetime->format('Y-m-d H:i:s'),
            ])
        );
    }
}
