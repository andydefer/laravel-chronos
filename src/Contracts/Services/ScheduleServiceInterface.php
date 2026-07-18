<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Interface for Schedule service operations.
 *
 * Defines the contract for high-level business operations to manage schedule
 * records. Schedules represent planned activities or appointments that can
 * be created, updated, cancelled, or completed. Each operation includes
 * validation and business rules enforcement.
 *
 * @example
 * // Create a new schedule for a specific entity
 * $service->for($doctor)->create(ScheduleRecord::from([
 *     'availability_id' => 1,
 *     'title' => 'Team Meeting',
 *     'start_time' => new DateTimeZuluVO('2024-01-01 10:00:00'),
 *     'end_time' => new DateTimeZuluVO('2024-01-01 11:00:00'),
 * ]));
 *
 * // Cancel an existing schedule
 * $service->cancel($scheduleId);
 *
 * // Find all schedules with a specific status
 * $completedSchedules = $service->findByStatus(ScheduleStatus::COMPLETED);
 */
interface ScheduleServiceInterface
{
    /**
     * Sets the schedulable entity context for subsequent operations.
     *
     * This method allows you to define the entity (e.g., Doctor, User) that
     * the schedule operations will be scoped to. When used, the entity type
     * and ID will be automatically injected into records.
     *
     * @param  Model  $schedulable  The schedulable entity (e.g., Doctor::find(42))
     * @return self Returns the service instance for method chaining
     *
     * @example
     * $service->for($doctor)->create($record);
     * $service->for($doctor)->findByStatus(ScheduleStatus::BOOKED);
     */
    public function for(Model $schedulable): self;

    /**
     * Creates a new schedule record.
     *
     * Validates the record against business rules before creation. If validation
     * passes, the record is persisted and the created model is returned.
     * If the service is scoped via for(), the schedulable_type and schedulable_id
     * are automatically injected.
     *
     * @param  ScheduleRecord  $record  The schedule data to create
     * @return Schedule The newly created schedule model
     *
     * @throws ValidationException When the record fails business rule validation
     * @throws Throwable When an unexpected error occurs during creation
     *
     * @example
     * // With for() - auto-injects schedulable_type and schedulable_id
     * $schedule = $service->for($doctor)->create(ScheduleRecord::from([
     *     'availability_id' => 1,
     *     'title' => 'Consultation',
     *     'start_datetime' => '2024-01-15T10:00:00Z',
     *     'end_datetime' => '2024-01-15T10:30:00Z',
     *     'status' => ScheduleStatus::BOOKED,
     * ]));
     *
     * // Without for() - must specify schedulable_type and schedulable_id
     * $schedule = $service->create(ScheduleRecord::from([
     *     'availability_id' => 1,
     *     'schedulable_type' => Doctor::class,
     *     'schedulable_id' => 42,
     *     'title' => 'Consultation',
     *     'start_datetime' => '2024-01-15T10:00:00Z',
     *     'end_datetime' => '2024-01-15T10:30:00Z',
     *     'status' => ScheduleStatus::BOOKED,
     * ]));
     */
    public function create(ScheduleRecord $record): Schedule;

    /**
     * Updates an existing schedule record.
     *
     * Finds the existing record, validates the updated data, and applies the
     * changes. The operation is atomic and includes mutation tracking.
     *
     * @param  int  $id  The ID of the schedule to update
     * @param  ScheduleRecord  $record  The updated schedule data
     * @return Schedule The updated schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the updated data fails validation
     * @throws Throwable When an unexpected error occurs during update
     */
    public function update(int $id, ScheduleRecord $record): Schedule;

    /**
     * Deletes a schedule record.
     *
     * Performs validation before deletion to ensure business rules are respected.
     *
     * @param  int  $id  The ID of the schedule to delete
     * @return bool True if the deletion was successful
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When validation fails
     * @throws Throwable When an unexpected error occurs during deletion
     */
    public function delete(int $id): bool;

    /**
     * Finds a schedule by its ID.
     *
     * @param  int  $id  The schedule ID to find
     * @return Schedule|null The schedule model or null if not found
     */
    public function find(int $id): ?Schedule;

    /**
     * Finds all schedules associated with a specific availability.
     *
     * @param  int  $availabilityId  The availability ID to filter by
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByAvailability(int $availabilityId): Collection;

    /**
     * Finds all schedules for a given schedulable entity.
     *
     * If the service is scoped via for(), you can omit the parameter.
     *
     * @param  Model|null  $schedulable  The schedulable entity, or null to use the scoped entity
     * @return Collection<int, Schedule> Collection of schedule models
     *
     * @throws \RuntimeException When no schedulable entity is provided and none is scoped
     *
     * @example
     * // Using scoped entity
     * $schedules = $service->for($doctor)->findBySchedulable();
     *
     * // Using explicit entity
     * $schedules = $service->findBySchedulable($doctor);
     */
    public function findBySchedulable(?Model $schedulable = null): Collection;

    /**
     * Finds schedules by their current status.
     *
     * @param  ScheduleStatus  $status  The status to filter by
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection;

    /**
     * Finds schedules that occur on a specific date.
     *
     * @param  DateTimeZuluVO  $date  The date to search for schedules
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection;

    /**
     * Finds schedules that fall within a date range.
     *
     * @param  DateTimeZuluVO  $start  The start of the date range
     * @param  DateTimeZuluVO  $end  The end of the date range
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of schedule models
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection;

    /**
     * Searches for schedules by title text.
     *
     * Performs a case-insensitive search on the title field.
     *
     * @param  string  $search  The search term to look for in the title
     * @param  int|null  $availabilityId  Optional availability filter
     * @return Collection<int, Schedule> Collection of matching schedule models
     */
    public function searchByTitle(string $search, ?int $availabilityId = null): Collection;

    /**
     * Cancels a schedule.
     *
     * Transitions the schedule to the CANCELLED status. This operation is
     * only allowed if the schedule can be cancelled according to business
     * rules (e.g., not already completed or cancelled).
     *
     * @param  int  $id  The ID of the schedule to cancel
     * @return Schedule The cancelled schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the schedule cannot be cancelled
     * @throws Throwable When an unexpected error occurs during cancellation
     */
    public function cancel(int $id): Schedule;

    /**
     * Completes a schedule.
     *
     * Transitions the schedule to the COMPLETED status. This operation is
     * only allowed if the schedule can be completed according to business
     * rules (e.g., not already completed or cancelled).
     *
     * @param  int  $id  The ID of the schedule to complete
     * @return Schedule The completed schedule model
     *
     * @throws ModelNotFoundException When no schedule exists with the given ID
     * @throws ValidationException When the schedule cannot be completed
     * @throws Throwable When an unexpected error occurs during completion
     */
    public function complete(int $id): Schedule;

    /**
     * Checks if a schedule can be cancelled.
     *
     * Determines if the schedule is in a state that allows cancellation
     * based on business rules.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be cancelled
     */
    public function canBeCancelled(Schedule $schedule): bool;

    /**
     * Checks if a schedule can be completed.
     *
     * Determines if the schedule is in a state that allows completion
     * based on business rules.
     *
     * @param  Schedule  $schedule  The schedule to check
     * @return bool True if the schedule can be completed
     */
    public function canBeCompleted(Schedule $schedule): bool;
}
