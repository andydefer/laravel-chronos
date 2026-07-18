<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

/**
 * Interface for Schedule service operations.
 *
 * Provides high-level business operations for managing schedules,
 * including creation, updates, deletion with validation and business rules.
 */
interface ScheduleServiceInterface
{
    /**
     * Create a new schedule.
     *
     * @param  ScheduleRecord  $record  The schedule data
     * @return Schedule The created schedule
     *
     * @throws ValidationException If validation fails
     * @throws \Throwable If creation fails
     */
    public function create(ScheduleRecord $record): Schedule;

    /**
     * Update an existing schedule.
     *
     * @param  int  $id  The schedule ID
     * @param  ScheduleRecord  $record  The updated data
     * @return Schedule The updated schedule
     *
     * @throws ModelNotFoundException If schedule not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If update fails
     */
    public function update(int $id, ScheduleRecord $record): Schedule;

    /**
     * Delete a schedule.
     *
     * @param  int  $id  The schedule ID
     * @return bool True if deleted
     *
     * @throws ModelNotFoundException If schedule not found
     * @throws ValidationException If validation fails
     * @throws \Throwable If deletion fails
     */
    public function delete(int $id): bool;

    /**
     * Find a schedule by its ID.
     *
     * @param  int  $id  The schedule ID
     * @return Schedule|null The schedule or null if not found
     */
    public function find(int $id): ?Schedule;

    /**
     * Find schedules by availability.
     *
     * @param  int  $availabilityId  The availability ID
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Find schedules by schedulable entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find schedules by status.
     *
     * @param  ScheduleStatus  $status  The schedule status
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection;

    /**
     * Find schedules by date.
     *
     * @param  DateTimeZuluVO  $date  The date to search
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Find schedules in a date range.
     *
     * @param  DateTimeZuluVO  $start  The start date
     * @param  DateTimeZuluVO  $end  The end date
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedules
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection;

    /**
     * Search schedules by title.
     *
     * @param  string  $search  The search term
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of matching schedules
     */
    public function searchByTitle(string $search, ?int $availabilityId = null): Collection;

    /**
     * Cancel a schedule.
     *
     * @param  int  $id  The schedule ID
     * @return Schedule The cancelled schedule
     *
     * @throws ModelNotFoundException If schedule not found
     * @throws ValidationException If schedule cannot be cancelled
     */
    public function cancel(int $id): Schedule;

    /**
     * Complete a schedule.
     *
     * @param  int  $id  The schedule ID
     * @return Schedule The completed schedule
     *
     * @throws ModelNotFoundException If schedule not found
     * @throws ValidationException If schedule cannot be completed
     */
    public function complete(int $id): Schedule;

    /**
     * Check if a schedule can be cancelled.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be cancelled
     */
    public function canBeCancelled(Schedule $schedule): bool;

    /**
     * Check if a schedule can be completed.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be completed
     */
    public function canBeCompleted(Schedule $schedule): bool;
}
