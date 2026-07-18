<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Schedule, ScheduleRecord>
 */
interface ScheduleRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find schedules for a specific availability.
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Find schedules that overlap with a given time slot.
     * Utilisé par: NoTemporalConflictRule, TimeSlotWithinAvailabilityRule
     */
    public function findOverlapping(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find schedules with a specific status.
     */
    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection;

    /**
     * Find schedules by title.
     */
    public function searchByTitle(string $search, ?int $availabilityId = null): Collection;

    /**
     * Find schedules for a specific date.
     * Utilisé par: TimeSlotWithinAvailabilityRule
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Find schedules in a date range.
     * Utilisé par: TimeSlotWithinAvailabilityRule
     */
    public function findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null): Collection;

    /**
     * Find schedules by day of week.
     * Utilisé par: TimeSlotWithinAvailabilityRule
     */
    public function findByDayOfWeek(int $dayOfWeek, ?int $availabilityId = null): Collection;

    /**
     * Find schedules for a specific schedulable entity.
     * Utilisé par: EntityOwnershipConsistencyRule
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find schedules with invalid chronology.
     * Utilisé par: TimeSlotChronologyRule
     */
    public function findWithInvalidChronology(): Collection;

    /**
     * Find schedules with duration exceeding max.
     * Utilisé par: MaxDurationRule
     */
    public function findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection;

    /**
     * Find schedules violating buffer time.
     * Utilisé par: BufferTimeRule
     */
    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection;

    /**
     * Find schedules for a specific availability with a date range.
     */
    public function findByAvailabilityInDateRange(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
    ): Collection;

    /**
     * Find schedules that conflict with a time slot.
     * Utilisé par: NoTemporalConflictRule
     */
    public function findConflicting(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Check if a schedule has overlapping days.
     * Utilisé par: CrossDayAvailabilityRule
     */
    public function hasCrossDaySchedule(int $availabilityId): bool;
}
