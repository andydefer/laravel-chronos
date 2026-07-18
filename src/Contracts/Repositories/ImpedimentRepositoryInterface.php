<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Impediment, ImpedimentRecord>
 */
interface ImpedimentRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find impediments for a specific availability.
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Find impediments in a date range.
     * Utilisé par: TimeSlotWithinAvailabilityRule
     */
    public function findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null): Collection;

    /**
     * Find impediments that overlap with a given time slot.
     * Utilisé par: NoTemporalConflictRule
     */
    public function findOverlapping(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find impediments for a specific schedulable entity.
     * Utilisé par: EntityOwnershipConsistencyRule
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find impediments by reason.
     */
    public function searchByReason(string $search, ?int $availabilityId = null): Collection;

    /**
     * Find impediments by date.
     * Utilisé par: TimeSlotWithinAvailabilityRule
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Find impediments for a specific availability in a date range.
     */
    public function findByAvailabilityInDateRange(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
    ): Collection;

    /**
     * Find active impediments.
     */
    public function findActive(?int $availabilityId = null): Collection;

    /**
     * Find impediments that conflict with a time slot.
     * Utilisé par: NoTemporalConflictRule
     */
    public function findConflicting(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find impediments with invalid chronology.
     * Utilisé par: TimeSlotChronologyRule
     */
    public function findWithInvalidChronology(): Collection;

    /**
     * Find impediments with duration exceeding max.
     * Utilisé par: MaxDurationRule
     */
    public function findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection;

    /**
     * Find impediments violating buffer time.
     * Utilisé par: BufferTimeRule
     */
    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection;
}
