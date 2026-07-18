<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

/**
 * Interface for Impediment service operations.
 *
 * Provides high-level business operations for managing impediments,
 * including creation, updates, deletion with validation and business rules.
 */
interface ImpedimentServiceInterface
{
    /**
     * Create a new impediment.
     *
     * @param  ImpedimentRecord  $record  The impediment data
     * @return Impediment The created impediment
     *
     * @throws ValidationException If validation fails
     * @throws \Throwable If creation fails
     */
    public function create(ImpedimentRecord $record): Impediment;

    /**
     * Update an existing impediment.
     *
     * @param  int  $id  The impediment ID
     * @param  ImpedimentRecord  $record  The updated data
     * @return Impediment The updated impediment
     *
     * @throws ModelNotFoundException If impediment not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If update fails
     */
    public function update(int $id, ImpedimentRecord $record): Impediment;

    /**
     * Delete an impediment.
     *
     * @param  int  $id  The impediment ID
     * @return bool True if deleted
     *
     * @throws ModelNotFoundException If impediment not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If deletion fails
     */
    public function delete(int $id): bool;

    /**
     * Find an impediment by its ID.
     *
     * @param  int  $id  The impediment ID
     * @return Impediment|null The impediment or null if not found
     */
    public function find(int $id): ?Impediment;

    /**
     * Find impediments by availability.
     *
     * @param  int  $availabilityId  The availability ID
     * @return Collection<int, Impediment> Collection of impediments
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Find impediments by schedulable entity.
     * Traverses through the availability relationship.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return Collection<int, Impediment> Collection of impediments
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find impediments by date.
     *
     * @param  DateTimeZuluVO  $date  The date to search
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of impediments
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Find impediments in a date range.
     *
     * @param  DateTimeZuluVO  $start  The start date
     * @param  DateTimeZuluVO  $end  The end date
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of impediments
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection;

    /**
     * Find active impediments (currently running).
     *
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of active impediments
     */
    public function findActive(?int $availabilityId = null): Collection;

    /**
     * Search impediments by reason.
     *
     * @param  string  $search  The search term
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of matching impediments
     */
    public function searchByReason(string $search, ?int $availabilityId = null): Collection;

    /**
     * Check if an impediment is currently active.
     *
     * @param  Impediment  $impediment  The impediment to check
     * @return bool True if the impediment is active
     */
    public function isActive(Impediment $impediment): bool;

    /**
     * Check if an impediment overlaps with a given time range.
     *
     * @param  Impediment  $impediment  The impediment to check
     * @param  DateTimeZuluVO  $start  The start of the time range
     * @param  DateTimeZuluVO  $end  The end of the time range
     * @return bool True if the impediment overlaps
     */
    public function overlapsWith(
        Impediment $impediment,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool;

    /**
     * Get schedules blocked by an impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of blocked schedules
     */
    public function getBlockedSchedules(Impediment $impediment): Collection;

    /**
     * Get schedules fully blocked by an impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of fully blocked schedules
     */
    public function getFullyBlockedSchedules(Impediment $impediment): Collection;

    /**
     * Get schedules partially blocked by an impediment.
     *
     * @param  Impediment  $impediment  The impediment
     * @return Collection<int, Schedule> Collection of partially blocked schedules
     */
    public function getPartiallyBlockedSchedules(Impediment $impediment): Collection;
}
