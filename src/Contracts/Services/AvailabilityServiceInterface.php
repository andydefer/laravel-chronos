<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Interface for Availability service operations.
 */
interface AvailabilityServiceInterface
{
    /**
     * Sets the schedulable entity context for subsequent operations.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return self Returns the service instance for method chaining
     */
    public function for(Model $schedulable): self;

    /**
     * Creates a new availability record.
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
     * If the service is scoped via for(), only availabilities belonging to
     * that entity will be returned.
     *
     * @param  int  $id  The availability ID to find
     * @return Availability|null The availability model or null if not found
     */
    public function find(int $id): ?Availability;

    /**
     * Finds all availabilities for a given schedulable entity.
     *
     * If the service is scoped via for(), you can omit the parameter.
     *
     * @param  Model|null  $schedulable  The schedulable entity, or null to use the scoped entity
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Availability> Collection of availability models
     *
     * @throws \RuntimeException When no schedulable entity is provided and none is scoped
     */
    public function findBySchedulable(?Model $schedulable = null, ?int $limit = null): Collection;

    /**
     * Finds all availabilities of a specific type.
     *
     * @param  string  $type  The availability type to filter by
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Availability> Collection of availability models
     */
    public function findByType(string $type, ?int $limit = null): Collection;

    /**
     * Finds availabilities that are active on a specific date.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $date  The date to check for active status
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveAtDate(
        Model $schedulable,
        DateTimeZuluVO $date,
        ?int $limit = null
    ): Collection;

    /**
     * Finds availabilities active within a date range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null
    ): Collection;

    /**
     * Checks if a schedulable entity exists.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return bool True if the entity exists, false otherwise
     */
    public function schedulableExists(Model $schedulable): bool;

    /**
     * Retrieves the model class name for a schedulable type.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return string|null The fully qualified class name or null if not found
     */
    public function getSchedulableModel(Model $schedulable): ?string;
}
