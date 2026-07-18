<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Interface for Availability service operations.
 *
 * Defines the contract for high-level business operations to manage availability
 * records. Each operation includes validation, business rules enforcement, and
 * mutation tracking. Implementations should wrap operations in appropriate
 * context managers for consistent error handling and auditing.
 *
 * @example
 * // Create a new availability
 * $service->create(new AvailabilityRecord([
 *     'schedulable_type' => 'user',
 *     'schedulable_id' => 1,
 *     'start_time' => new DateTimeZuluVO('2024-01-01 09:00:00'),
 *     'end_time' => new DateTimeZuluVO('2024-01-01 17:00:00'),
 * ]));
 *
 * // Find all availabilities for a schedulable entity
 * $availabilities = $service->findBySchedulable('user', 1);
 */
interface AvailabilityServiceInterface
{
    /**
     * Creates a new availability record.
     *
     * Validates the record against business rules before creation. If validation
     * passes, the record is persisted and the created model is returned.
     *
     * @param  AvailabilityRecord  $record  The availability data to create
     * @return Availability The newly created availability model
     *
     * @throws ValidationException When the record fails business rule validation
     * @throws Throwable When an unexpected error occurs during creation
     */
    public function create(AvailabilityRecord $record): Availability;

    /**
     * Updates an existing availability record.
     *
     * Finds the existing record, validates the updated data, and applies the
     * changes. The operation is atomic and includes mutation tracking.
     *
     * @param  int  $id  The ID of the availability to update
     * @param  AvailabilityRecord  $record  The updated availability data
     * @return Availability The updated availability model
     *
     * @throws ModelNotFoundException When no availability exists with the given ID
     * @throws ValidationException When the updated data fails validation
     * @throws Throwable When an unexpected error occurs during update
     */
    public function update(int $id, AvailabilityRecord $record): Availability;

    /**
     * Deletes an availability record.
     *
     * Performs validation before deletion to ensure business rules are respected.
     * Force deletion bypasses validation checks for scenarios where they would
     * block legitimate operations.
     *
     * @param  int  $id  The ID of the availability to delete
     * @param  bool  $force  When true, bypasses pre-deletion validation
     * @return bool True if the deletion was successful
     *
     * @throws ModelNotFoundException When no availability exists with the given ID
     * @throws ValidationException When validation fails and force is false
     * @throws Throwable When an unexpected error occurs during deletion
     */
    public function delete(int $id, bool $force = false): bool;

    /**
     * Finds an availability by its ID.
     *
     * @param  int  $id  The availability ID to find
     * @return Availability|null The availability model or null if not found
     */
    public function find(int $id): ?Availability;

    /**
     * Finds all availabilities for a given schedulable entity.
     *
     * Returns all availabilities associated with the specified entity type and ID,
     * regardless of their active status.
     *
     * @param  string  $schedulableType  The entity type (e.g., 'user', 'location')
     * @param  int  $schedulableId  The entity ID
     * @return Collection<int, Availability> Collection of availability models
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Finds all availabilities of a specific type.
     *
     * @param  string  $type  The availability type to filter by
     * @return Collection<int, Availability> Collection of availability models
     */
    public function findByType(string $type): Collection;

    /**
     * Finds availabilities that are active on a specific date.
     *
     * Returns availabilities that cover the given date, regardless of time.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $date  The date to check for active status
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveAtDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): Collection;

    /**
     * Finds availabilities active within a date range.
     *
     * Returns availabilities that overlap with the specified date range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveInDateRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection;

    /**
     * Checks if a schedulable entity exists.
     *
     * Validates that the referenced entity type and ID combination exists
     * in the system.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return bool True if the entity exists, false otherwise
     */
    public function schedulableExists(string $schedulableType, int $schedulableId): bool;

    /**
     * Retrieves the model class name for a schedulable type.
     *
     * Returns the fully qualified class name that represents the schedulable
     * entity type, or null if the type is not registered.
     *
     * @param  string  $schedulableType  The entity type to resolve
     * @return string|null The fully qualified class name or null if not found
     */
    public function getSchedulableModel(string $schedulableType): ?string;
}
