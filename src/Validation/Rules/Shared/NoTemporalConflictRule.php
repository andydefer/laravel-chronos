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
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

final class NoTemporalConflictRule implements ValidationRule
{
    public function getDescription(): string
    {
        return 'Prevents temporal conflicts between schedules and impediments on the same availability.';
    }

    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && ($context->isCreate() || $context->isUpdate());
    }

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
     * @return array{int|string, DateTimeZuluVO, DateTimeZuluVO, int|string|null}|null
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

    private function findConflictingSchedule(
        int|string $availabilityId,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        int|string|null $excludeId
    ): ?Schedule {
        $query = Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $endDatetime->toDateTimeString())
            ->where('end_datetime', '>', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    private function findConflictingImpediment(
        int|string $availabilityId,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        int|string|null $excludeId
    ): ?Impediment {
        $query = Impediment::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $endDatetime->toDateTimeString())
            ->where('end_datetime', '>', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

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
                'Time slot %s to %s conflicts with existing %s #%s (%s to %s).',
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
