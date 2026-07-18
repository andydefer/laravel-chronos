<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Collections\BlockedPeriodCollection;
use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for slot availability operations.
 *
 * Defines the contract for finding and generating available time slots within
 * availabilities, considering schedules and impediments as blockers. This service
 * is the core of the scheduling engine, handling complex availability calculations.
 *
 * @example
 * $service = new SlotService($availabilityService, $scheduleService, $impedimentService);
 *
 * // Find the next 30-minute slot for a user
 * $slot = $service->findNextSlot($user, now(), 30);
 *
 * // Get all available slots for a specific day
 * $slots = $service->findSlotsForDay($user, today(), 30);
 */
interface SlotServiceInterface
{
    /**
     * Finds the next available slot after a given time.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration of the slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVO|null The next available slot or null if none found
     */
    public function findNextSlot(
        Model $schedulable,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO;

    /**
     * Finds the previous available slot before a given time.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $before  The time before which to search
     * @param  int  $durationInMinutes  The required duration of the slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVO|null The previous available slot or null if none found
     */
    public function findPreviousSlot(
        Model $schedulable,
        DateTimeZuluVO $before,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO;

    /**
     * Finds all available slots within a date range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the search range
     * @param  DateTimeZuluVO  $end  The end of the search range
     * @param  int  $durationInMinutes  The required duration of each slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVOCollection Collection of available slots sorted by start time
     */
    public function findSlotsInRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection;

    /**
     * Finds all available slots for a specific day.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $date  The date to search
     * @param  int  $durationInMinutes  The required duration of each slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVOCollection Collection of available slots for the day
     */
    public function findSlotsForDay(
        Model $schedulable,
        DateTimeZuluVO $date,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection;

    /**
     * Checks if a specific time slot is available.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the slot to check
     * @param  DateTimeZuluVO  $end  The end of the slot to check
     * @param  int|null  $availabilityId  Optional specific availability to check
     * @return bool True if the exact slot is available
     */
    public function isSlotAvailable(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool;

    /**
     * Gets the next available start time for a given duration.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return DateTimeZuluVO|null The next available start time or null if none found
     */
    public function getNextAvailableStart(
        Model $schedulable,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?DateTimeZuluVO;

    /**
     * Checks if a schedulable entity has any availability for a given date.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $date  The date to check
     * @return bool True if the entity has availability on this date
     */
    public function hasAvailabilityOnDate(
        Model $schedulable,
        DateTimeZuluVO $date
    ): bool;

    /**
     * Gets all blocked time periods for a given date range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $availabilityId  Optional specific availability to analyze
     * @return BlockedPeriodCollection Collection of blocked periods
     */
    public function getBlockedPeriods(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): BlockedPeriodCollection;

    /**
     * Generates smaller slots by splitting a larger slot.
     *
     * @param  SlotVO  $slot  The slot to split
     * @param  int  $chunkDuration  The duration of each chunk in minutes
     * @return SlotVOCollection Collection of smaller slots
     */
    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration): SlotVOCollection;
}
