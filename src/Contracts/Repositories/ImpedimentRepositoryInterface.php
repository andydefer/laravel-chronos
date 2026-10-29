<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Impediment, ImpedimentRecord>
 */
interface ImpedimentRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find impediments for a specific availability.
     */
    public function findByAvailability(int|string $availabilityId, ?int $limit = null): Collection;

    /**
     * Find impediments in a date range.
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): Collection;

    /**
     * Find impediments that overlap with a given time slot.
     */
    public function findOverlapping(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null,
        ?int $limit = null,
    ): Collection;

    /**
     * Find impediments for a specific schedulable entity.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Impediment> Collection of impediments
     */
    public function findBySchedulable(Model $schedulable, ?int $limit = null): Collection;

    /**
     * Find impediments by reason.
     */
    public function searchByReason(string $search, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find impediments by date.
     */
    public function findByDate(DateTimeZuluVO $date, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find impediments for a specific availability in a date range.
     */
    public function findByAvailabilityInDateRange(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null,
    ): Collection;

    /**
     * Find active impediments.
     */
    public function findActive(int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Find impediments that conflict with a time slot.
     */
    public function findConflicting(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null,
        ?int $limit = null,
    ): Collection;

    /**
     * Find impediments with invalid chronology.
     */
    public function findWithInvalidChronology(?int $limit = null): Collection;

    /**
     * Find impediments with duration exceeding max.
     */
    public function findWithExceedingDuration(int|string $availabilityId, int $maxDurationMinutes, ?int $limit = null): Collection;

    /**
     * Find impediments violating buffer time.
     */
    public function findViolatingBufferTime(int|string $availabilityId, int $bufferMinutes, ?int $limit = null): Collection;

    /**
     * Get schedules that are blocked by this impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of blocked schedules
     */
    public function getBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection;

    /**
     * Get schedules that are completely within this impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of fully blocked schedules
     */
    public function getFullyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection;

    /**
     * Get schedules that partially overlap with this impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of partially blocked schedules
     */
    public function getPartiallyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection;
}
