<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Schedule, ScheduleRecord>
 */
interface ScheduleRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find schedules for a specific availability.
     */
    public function findByAvailability(int|string $availabilityId, ?int $limit = null): Collection;

    /**
     * Find schedules that overlap with a given time slot.
     */
    public function findOverlapping(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null,
        ?int $limit = null,
    ): Collection;

    /**
     * Find schedules with a specific status.
     */
    public function findByStatus(ScheduleStatus $status, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find schedules by title.
     */
    public function searchByTitle(string $search, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find schedules for a specific date.
     */
    public function findByDate(DateTimeZuluVO $date, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find schedules in a date range.
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): Collection;

    /**
     * Find schedules by day of week.
     */
    public function findByDayOfWeek(int $dayOfWeek, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find schedules for a specific schedulable entity.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findBySchedulable(Model $schedulable, ?int $limit = null): Collection;

    /**
     * Find schedules with invalid chronology.
     */
    public function findWithInvalidChronology(?int $limit = null): Collection;

    /**
     * Find schedules with duration exceeding max.
     */
    public function findWithExceedingDuration(int|string $availabilityId, int $maxDurationMinutes, ?int $limit = null): Collection;

    /**
     * Find schedules violating buffer time.
     */
    public function findViolatingBufferTime(int|string $availabilityId, int $bufferMinutes, ?int $limit = null): Collection;

    /**
     * Find schedules for a specific availability with a date range.
     */
    public function findByAvailabilityInDateRange(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null,
    ): Collection;

    /**
     * Find schedules that conflict with a time slot.
     */
    public function findConflicting(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null,
        ?int $limit = null,
    ): Collection;

    /**
     * Check if a schedule has overlapping days.
     */
    public function hasCrossDaySchedule(int|string $availabilityId): bool;
}
