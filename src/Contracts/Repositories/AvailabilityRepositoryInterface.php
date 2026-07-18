<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Repositories;

use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<Availability, AvailabilityRecord>
 */
interface AvailabilityRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Find availabilities for a specific schedulable entity.
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find availabilities that contain a specific day of the week.
     * Utilisé par: AvailabilityDaysFormatRule, DaysWithinValidityPeriodRule
     */
    public function findByDay(string $schedulableType, int $schedulableId, WeekDay $day): Collection;

    /**
     * Find availabilities that overlap with a given time range.
     * Utilisé par: AvailabilityNoOverlapRule
     */
    public function findOverlapping(
        string $schedulableType,
        int $schedulableId,
        WeekDay $day,
        TimeZuluVO $startTime,
        TimeZuluVO $endTime,
        DateTimeZuluVO $validityStart,
        DateTimeZuluVO $validityEnd,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find availabilities with validity period containing a date.
     * Utilisé par: DaysWithinValidityPeriodRule
     */
    public function findActiveAtDate(string $schedulableType, int $schedulableId, DateTimeZuluVO $date): Collection;

    /**
     * Find availabilities with validity period overlapping a range.
     * Utilisé par: AvailabilityNoOverlapRule
     */
    public function findActiveInDateRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection;

    /**
     * Find availabilities that cross midnight.
     * Utilisé par: CrossDayAvailabilityRule
     */
    public function findCrossDayAvailabilities(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find availabilities with duration less than minimum.
     * Utilisé par: AvailabilityMinimumDurationRule
     */
    public function findShortDurations(string $schedulableType, int $schedulableId, int $minMinutes): Collection;

    /**
     * Find availabilities with invalid date ranges.
     * Utilisé par: AvailabilityValidDateRangeRule
     */
    public function findInvalidDateRanges(string $schedulableType, int $schedulableId): Collection;

    /**
     * Find availabilities with future schedules.
     * Utilisé par: NoFutureBookingsOnDeleteRule
     */
    public function findWithFutureSchedules(int $availabilityId, DateTimeZuluVO $now): bool;

    /**
     * Find availabilities by type.
     */
    public function findByType(string $type): Collection;

    /**
     * Check if a schedulable entity exists.
     * Utilisé par: SchedulableExistsRule
     */
    public function schedulableExists(string $schedulableType, int $schedulableId): bool;

    /**
     * Get the model class for a schedulable type.
     * Utilisé par: SchedulableExistsRule
     */
    public function getSchedulableModel(string $schedulableType): ?string;
}
