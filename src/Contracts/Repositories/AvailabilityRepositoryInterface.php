<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Availability, AvailabilityRecord>
 */
interface AvailabilityRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find availabilities for a specific schedulable entity.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Availability> Collection of availabilities
     */
    public function findBySchedulable(Model $schedulable): Collection;

    /**
     * Find availabilities that contain a specific day of the week.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  WeekDay  $day  The day to filter by
     * @return Collection<int, Availability> Collection of availabilities
     */
    public function findByDay(Model $schedulable, WeekDay $day): Collection;

    /**
     * Find availabilities that overlap with a given time range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  WeekDay  $day  The day to check
     * @param  TimeZuluVO  $startTime  The start time
     * @param  TimeZuluVO  $endTime  The end time
     * @param  DateTimeZuluVO  $validityStart  The start of validity period
     * @param  DateTimeZuluVO  $validityEnd  The end of validity period
     * @param  int|null  $excludeId  Optional ID to exclude
     * @return Collection<int, Availability> Collection of overlapping availabilities
     */
    public function findOverlapping(
        Model $schedulable,
        WeekDay $day,
        TimeZuluVO $startTime,
        TimeZuluVO $endTime,
        DateTimeZuluVO $validityStart,
        DateTimeZuluVO $validityEnd,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find availabilities with validity period containing a date.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $date  The date to check
     * @return Collection<int, Availability> Collection of active availabilities
     */
    public function findActiveAtDate(Model $schedulable, DateTimeZuluVO $date): Collection;

    /**
     * Find availabilities with validity period overlapping a range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $excludeId  Optional ID to exclude
     * @return Collection<int, Availability> Collection of availabilities in range
     */
    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find availabilities that cross midnight.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Availability> Collection of cross-day availabilities
     */
    public function findCrossDayAvailabilities(Model $schedulable): Collection;

    /**
     * Find availabilities with duration less than minimum.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  int  $minMinutes  The minimum duration in minutes
     * @return Collection<int, Availability> Collection of short duration availabilities
     */
    public function findShortDurations(Model $schedulable, int $minMinutes): Collection;

    /**
     * Find availabilities with invalid date ranges.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return Collection<int, Availability> Collection of invalid availabilities
     */
    public function findInvalidDateRanges(Model $schedulable): Collection;

    /**
     * Find availabilities with future schedules.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $now  The current time
     * @return bool True if future schedules exist
     */
    public function findWithFutureSchedules(int $availabilityId, DateTimeZuluVO $now): bool;

    /**
     * Find availabilities by type.
     *
     * @param  string  $type  The availability type
     * @return Collection<int, Availability> Collection of availabilities
     */
    public function findByType(string $type): Collection;

    /**
     * Check if a schedulable entity exists.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return bool True if the entity exists
     */
    public function schedulableExists(Model $schedulable): bool;

    /**
     * Get the model class for a schedulable type.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @return string|null The model class name
     */
    public function getSchedulableModel(Model $schedulable): ?string;
}
