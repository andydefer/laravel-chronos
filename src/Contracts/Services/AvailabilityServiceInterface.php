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
 *
 * Defines the contract for high-level business operations to manage availability
 * records. Each operation includes validation, business rules enforcement, and
 * mutation tracking. Implementations should wrap operations in appropriate
 * context managers for consistent error handling and auditing.
 *
 * @example
 * // Create a new availability with scoping
 * $service->for($doctor)->create(AvailabilityRecord::from([
 *     'name' => 'Consultations',
 *     'days' => ['monday', 'wednesday', 'friday'],
 *     'daily_start' => '09:00:00',
 *     'daily_end' => '17:00:00',
 *     'validity_start' => '2024-01-01T00:00:00Z',
 *     'validity_end' => '2024-12-31T23:59:59Z',
 * ]));
 *
 * // Find all availabilities for a schedulable entity
 * $availabilities = $service->findBySchedulable($doctor);
 *
 * // Find using scoped entity
 * $availabilities = $service->for($doctor)->findBySchedulable();
 */
interface AvailabilityServiceInterface
{
    /**
     * Sets the schedulable entity context for subsequent operations.
     *
     * This method allows you to define the entity (e.g., Doctor, User) that
     * the availability operations will be scoped to. When used, the entity type
     * and ID will be automatically injected into records.
     *
     * @param  Model  $schedulable  The schedulable entity (e.g., Doctor::find(42))
     * @return self Returns the service instance for method chaining
     *
     * @example
     * $service->for($doctor)->create($record);
     * $service->for($doctor)->findBySchedulable();
     */
    public function for(Model $schedulable): self;

    /**
     * Creates a new availability record.
     *
     * Validates the record against business rules before creation. If validation
     * passes, the record is persisted and the created model is returned.
     * If the service is scoped via for(), the schedulable_type and schedulable_id
     * are automatically injected.
     *
     * @param  AvailabilityRecord  $record  The availability data to create
     * @return Availability The newly created availability model
     *
     * @throws ValidationException When the record fails business rule validation
     * @throws Throwable When an unexpected error occurs during creation
     *
     * @example
     * // With for() - auto-injects schedulable_type and schedulable_id
     * $availability = $service->for($doctor)->create(AvailabilityRecord::from([
     *     'name' => 'Consultations',
     *     'days' => ['monday', 'wednesday', 'friday'],
     *     'daily_start' => '09:00:00',
     *     'daily_end' => '17:00:00',
     *     'validity_start' => '2024-01-01T00:00:00Z',
     *     'validity_end' => '2024-12-31T23:59:59Z',
     * ]));
     *
     * // Without for() - must specify schedulable_type and schedulable_id
     * $availability = $service->create(AvailabilityRecord::from([
     *     'name' => 'Consultations',
     *     'days' => ['monday', 'wednesday', 'friday'],
     *     'daily_start' => '09:00:00',
     *     'daily_end' => '17:00:00',
     *     'validity_start' => '2024-01-01T00:00:00Z',
     *     'validity_end' => '2024-12-31T23:59:59Z',
     *     'schedulable_type' => Doctor::class,
     *     'schedulable_id' => 42,
     * ]));
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
     * @return Collection<int, Availability> Collection of availability models
     *
     * @throws \RuntimeException When no schedulable entity is provided and none is scoped
     *
     * @example
     * // Using scoped entity
     * $availabilities = $service->for($doctor)->findBySchedulable();
     *
     * // Using explicit entity
     * $availabilities = $service->findBySchedulable($doctor);
     */
    public function findBySchedulable(?Model $schedulable = null): Collection;

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
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $date  The date to check for active status
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveAtDate(
        Model $schedulable,
        DateTimeZuluVO $date
    ): Collection;

    /**
     * Finds availabilities active within a date range.
     *
     * Returns availabilities that overlap with the specified date range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @return Collection<int, Availability> Collection of active availability models
     */
    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection;

    /**
     * Checks if a schedulable entity exists.
     *
     * Validates that the referenced entity exists in the system.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return bool True if the entity exists, false otherwise
     */
    public function schedulableExists(Model $schedulable): bool;

    /**
     * Retrieves the model class name for a schedulable type.
     *
     * Returns the fully qualified class name that represents the schedulable
     * entity, or null if the type is not registered.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return string|null The fully qualified class name or null if not found
     */
    public function getSchedulableModel(Model $schedulable): ?string;
}
