<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Interface for Impediment service operations.
 *
 * Defines the contract for high-level business operations to manage impediment
 * records, which represent blocks or restrictions on availability. Impediments
 * can affect schedules by partially or fully blocking them, and are associated
 * with availability records.
 *
 * @example
 * // Create an impediment
 * $service->create(new ImpedimentRecord([
 *     'availability_id' => 1,
 *     'reason' => 'Team meeting',
 *     'start_time' => new DateTimeZuluVO('2024-01-01 10:00:00'),
 *     'end_time' => new DateTimeZuluVO('2024-01-01 11:00:00'),
 * ]));
 *
 * // Find all active impediments
 * $activeImpediments = $service->findActive();
 */
interface ImpedimentServiceInterface
{
    /**
     * Creates a new impediment record.
     *
     * Validates the record against business rules before creation. If validation
     * passes, the record is persisted and the created model is returned.
     *
     * @param  ImpedimentRecord  $record  The impediment data to create
     * @return Impediment The newly created impediment model
     *
     * @throws ValidationException When the record fails business rule validation
     * @throws Throwable When an unexpected error occurs during creation
     */
    public function create(ImpedimentRecord $record): Impediment;

    /**
     * Updates an existing impediment record.
     *
     * Finds the existing record, validates the updated data, and applies the
     * changes. The operation is atomic and includes mutation tracking.
     *
     * @param  int  $id  The ID of the impediment to update
     * @param  ImpedimentRecord  $record  The updated impediment data
     * @return Impediment The updated impediment model
     *
     * @throws ModelNotFoundException When no impediment exists with the given ID
     * @throws ValidationException When the updated data fails validation
     * @throws Throwable When an unexpected error occurs during update
     */
    public function update(int $id, ImpedimentRecord $record): Impediment;

    /**
     * Deletes an impediment record.
     *
     * Performs validation before deletion to ensure business rules are respected.
     *
     * @param  int  $id  The ID of the impediment to delete
     * @return bool True if the deletion was successful
     *
     * @throws ModelNotFoundException When no impediment exists with the given ID
     * @throws ValidationException When validation fails
     * @throws Throwable When an unexpected error occurs during deletion
     */
    public function delete(int $id): bool;

    /**
     * Finds an impediment by its ID.
     *
     * @param  int  $id  The impediment ID to find
     * @return Impediment|null The impediment model or null if not found
     */
    public function find(int $id): ?Impediment;

    /**
     * Finds all impediments associated with a specific availability.
     *
     * @param  int  $availabilityId  The availability ID to filter by
     * @return Collection<int, Impediment> Collection of impediment models
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Finds all impediments for a given schedulable entity.
     *
     * Traverses through the availability relationship to find impediments
     * associated with the specified entity.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Impediment> Collection of impediment models
     */
    public function findBySchedulable(Model $schedulable): Collection;

    /**
     * Finds impediments that occur on a specific date.
     *
     * @param  DateTimeZuluVO  $date  The date to search for impediments
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of impediment models
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Finds impediments that fall within a date range.
     *
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of impediment models
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection;

    /**
     * Finds currently active impediments.
     *
     * Active impediments are those that are currently running based on
     * the current system time.
     *
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of active impediment models
     */
    public function findActive(?int $availabilityId = null): Collection;

    /**
     * Searches for impediments by reason text.
     *
     * Performs a case-insensitive search on the reason field.
     *
     * @param  string  $search  The search term to look for in the reason
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Impediment> Collection of matching impediment models
     */
    public function searchByReason(string $search, ?int $availabilityId = null): Collection;

    /**
     * Checks if an impediment is currently active.
     *
     * An impediment is active if the current time falls within its date range.
     *
     * @param  Impediment  $impediment  The impediment to check
     * @return bool True if the impediment is currently active
     */
    public function isActive(Impediment $impediment): bool;

    /**
     * Checks if an impediment overlaps with a given time range.
     *
     * Determines if the impediment's time range intersects with the provided
     * time range.
     *
     * @param  Impediment  $impediment  The impediment to check
     * @param  DateTimeZuluVO  $start  The start of the time range to check
     * @param  DateTimeZuluVO  $end  The end of the time range to check
     * @return bool True if the impediment overlaps with the time range
     */
    public function overlapsWith(
        Impediment $impediment,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool;

    /**
     * Retrieves all schedules blocked by an impediment.
     *
     * Returns all schedules whose time ranges intersect with the impediment
     * in any way (fully or partially).
     *
     * @param  Impediment  $impediment  The impediment to analyze
     * @return Collection<int, Schedule> Collection of blocked schedule models
     */
    public function getBlockedSchedules(Impediment $impediment): Collection;

    /**
     * Retrieves schedules fully blocked by an impediment.
     *
     * Returns schedules whose entire time range falls within the impediment's
     * time range, meaning they are completely unavailable.
     *
     * @param  Impediment  $impediment  The impediment to analyze
     * @return Collection<int, Schedule> Collection of fully blocked schedule models
     */
    public function getFullyBlockedSchedules(Impediment $impediment): Collection;

    /**
     * Retrieves schedules partially blocked by an impediment.
     *
     * Returns schedules that partially overlap with the impediment, meaning
     * only a portion of their time range is blocked.
     *
     * @param  Impediment  $impediment  The impediment to analyze
     * @return Collection<int, Schedule> Collection of partially blocked schedule models
     */
    public function getPartiallyBlockedSchedules(Impediment $impediment): Collection;
}
