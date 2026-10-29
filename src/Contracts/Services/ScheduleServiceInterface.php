<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

interface ScheduleServiceInterface
{
    /**
     * Sets the schedulable entity context for subsequent operations.
     *
     * @param  Model  $schedulable  The schedulable entity (e.g., Doctor::find(42))
     * @return self Returns the service instance for method chaining
     */
    public function for(Model $schedulable): self;

    /**
     * Creates a new schedule record.
     *
     * @param  ScheduleRecord  $record  The schedule data to create
     * @return Schedule The newly created schedule model
     *
     * @throws ValidationException When the record fails business rule validation
     * @throws Throwable When an unexpected error occurs during creation
     */
    public function create(ScheduleRecord $record): Schedule;

    /**
     * Updates an existing schedule record.
     *
     * @param  int|string  $id  The ID of the schedule to update
     * @param  ScheduleRecord  $record  The updated schedule data
     * @return Schedule The updated schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the updated data fails validation
     * @throws Throwable When an unexpected error occurs during update
     */
    public function update(int|string $id, ScheduleRecord $record): Schedule;

    /**
     * Deletes a schedule record.
     *
     * @param  int|string  $id  The ID of the schedule to delete
     * @return bool True if the deletion was successful
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When validation fails
     * @throws Throwable When an unexpected error occurs during deletion
     */
    public function delete(int|string $id): bool;

    /**
     * Finds a schedule by its ID.
     *
     * @param  int|string  $id  The schedule ID to find
     * @return Schedule|null The schedule model or null if not found
     */
    public function find(int|string $id): ?Schedule;

    /**
     * Finds all schedules associated with a specific availability.
     *
     * @param  int|string  $availabilityId  The availability ID to filter by
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByAvailability(int|string $availabilityId, ?int $limit = null): Collection;

    /**
     * Finds all schedules for a given schedulable entity.
     *
     * @param  Model|null  $schedulable  The schedulable entity, or null to use the scoped entity
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of schedule models
     *
     * @throws \RuntimeException When no schedulable entity is provided and none is scoped
     */
    public function findBySchedulable(?Model $schedulable = null, ?int $limit = null): Collection;

    /**
     * Finds schedules by their current status.
     *
     * @param  ScheduleStatus  $status  The status to filter by
     * @param  int|string|null  $availabilityId  Optional availability filter
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByStatus(ScheduleStatus $status, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Finds schedules that occur on a specific date.
     *
     * @param  DateTimeZuluVO  $date  The date to search for schedules
     * @param  int|string|null  $availabilityId  Optional availability filter
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByDate(DateTimeZuluVO $date, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Finds schedules that fall within a date range.
     *
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @param  int|string|null  $availabilityId  Optional availability filter
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): Collection;

    /**
     * Searches for schedules by title text.
     *
     * @param  string  $search  The search term to look for in the title
     * @param  int|string|null  $availabilityId  Optional availability filter
     * @param  int|null  $limit  Maximum number of results to return
     * @return Collection<int, Schedule> Collection of matching schedule models
     */
    public function searchByTitle(string $search, int|string|null $availabilityId = null, ?int $limit = null): Collection;

    /**
     * Cancels a schedule.
     *
     * @param  int|string  $id  The ID of the schedule to cancel
     * @return Schedule The cancelled schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the schedule cannot be cancelled
     * @throws Throwable When an unexpected error occurs during cancellation
     */
    public function cancel(int|string $id): Schedule;

    /**
     * Completes a schedule.
     *
     * @param  int|string  $id  The ID of the schedule to complete
     * @return Schedule The completed schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the schedule cannot be completed
     * @throws Throwable When an unexpected error occurs during completion
     */
    public function complete(int|string $id): Schedule;

    /**
     * Checks if a schedule can be cancelled.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be cancelled
     */
    public function canBeCancelled(Schedule $schedule): bool;

    /**
     * Checks if a schedule can be completed.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be completed
     */
    public function canBeCompleted(Schedule $schedule): bool;
}
