<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

/**
 * Interface for Availability service operations.
 *
 * Provides high-level business operations for managing availabilities,
 * including creation, updates, deletion with validation and business rules.
 */
interface AvailabilityServiceInterface
{
    /**
     * Create a new availability.
     *
     * @param  AvailabilityRecord  $record  The availability data
     * @return Availability The created availability
     *
     * @throws ValidationException If validation fails
     * @throws \Throwable If creation fails
     */
    public function create(AvailabilityRecord $record): Availability;

    /**
     * Update an existing availability.
     *
     * @param  int  $id  The availability ID
     * @param  AvailabilityRecord  $record  The updated data
     * @return Availability The updated availability
     *
     * @throws ModelNotFoundException If availability not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If update fails
     */
    public function update(int $id, AvailabilityRecord $record): Availability;

    /**
     * Delete an availability.
     *
     * @param  int  $id  The availability ID
     * @param  bool  $force  Force deletion even if there are future bookings
     * @return bool True if deleted
     *
     * @throws ModelNotFoundException If availability not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If deletion fails
     */
    public function delete(int $id, bool $force = false): bool;

    /**
     * Find an availability by its ID.
     *
     * @param  int  $id  The availability ID
     * @return Availability|null The availability or null if not found
     */
    public function find(int $id): ?Availability;

    /**
     * Find availabilities by schedulable entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return Collection<int, Availability> Collection of availabilities
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find availabilities by type.
     *
     * @param  string  $type  The availability type
     * @return Collection<int, Availability> Collection of availabilities
     */
    public function findByType(string $type): Collection;

    /**
     * Find availabilities active on a specific date.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $date  The date to check
     * @return Collection<int, Availability> Collection of active availabilities
     */
    public function findActiveAtDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): Collection;

    /**
     * Find availabilities active within a date range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @return Collection<int, Availability> Collection of active availabilities
     */
    public function findActiveInDateRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection;

    /**
     * Check if a schedulable entity exists.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return bool True if the entity exists
     */
    public function schedulableExists(string $schedulableType, int $schedulableId): bool;

    /**
     * Get the schedulable model class.
     *
     * @param  string  $schedulableType  The entity type
     * @return string|null The class name or null if not found
     */
    public function getSchedulableModel(string $schedulableType): ?string;
}
