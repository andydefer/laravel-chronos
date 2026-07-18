<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;

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
 * $slot = $service->findNextSlot('user', 1, now(), 30);
 *
 * // Get all available slots for a specific day
 * $slots = $service->findSlotsForDay('user', 1, today(), 30);
 */
interface SlotServiceInterface
{
    /**
     * Finds the next available slot after a given time.
     *
     * Searches forward from the specified time to find the first available
     * slot of the required duration for the given schedulable entity.
     *
     * @param  string  $schedulableType  The entity type (e.g., 'App\Models\User')
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration of the slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVO|null The next available slot or null if none found
     */
    public function findNextSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO;

    /**
     * Finds the previous available slot before a given time.
     *
     * Searches backward from the specified time to find the last available
     * slot of the required duration for the given schedulable entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $before  The time before which to search
     * @param  int  $durationInMinutes  The required duration of the slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVO|null The previous available slot or null if none found
     */
    public function findPreviousSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $before,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO;

    /**
     * Finds all available slots within a date range.
     *
     * Generates all possible slots of the required duration that fall within
     * the specified date range for the given schedulable entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the search range
     * @param  DateTimeZuluVO  $end  The end of the search range
     * @param  int  $durationInMinutes  The required duration of each slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVOCollection Collection of available slots sorted by start time
     */
    public function findSlotsInRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection;

    /**
     * Finds all available slots for a specific day.
     *
     * Convenience method that finds all slots of the required duration
     * available on the given date for the specified schedulable entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $date  The date to search
     * @param  int  $durationInMinutes  The required duration of each slot in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return SlotVOCollection Collection of available slots for the day
     */
    public function findSlotsForDay(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection;

    /**
     * Checks if a specific time slot is available.
     *
     * Verifies that the exact time range is available for the given entity
     * without any conflicts with schedules or impediments.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the slot to check
     * @param  DateTimeZuluVO  $end  The end of the slot to check
     * @param  int|null  $availabilityId  Optional specific availability to check
     * @return bool True if the exact slot is available
     */
    public function isSlotAvailable(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool;

    /**
     * Gets the next available start time for a given duration.
     *
     * Convenience method that returns only the start time of the next
     * available slot, without the full SlotVO object.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration in minutes
     * @param  int|null  $availabilityId  Optional specific availability to search within
     * @return DateTimeZuluVO|null The next available start time or null if none found
     */
    public function getNextAvailableStart(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?DateTimeZuluVO;

    /**
     * Checks if a schedulable entity has any availability for a given date.
     *
     * Quick check to determine if the entity has any active availability
     * on the specified date.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $date  The date to check
     * @return bool True if the entity has availability on this date
     */
    public function hasAvailabilityOnDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): bool;

    /**
     * Gets all blocked time periods for a given date range.
     *
     * Returns all periods that are blocked by schedules or impediments
     * within the specified date range for the given entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $availabilityId  Optional specific availability to analyze
     * @return array<array{start: DateTimeZuluVO, end: DateTimeZuluVO, type: string, id: int}>
     *                                                                                         Array of blocked periods with their metadata
     */
    public function getBlockedPeriods(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): array;

    /**
     * Generates smaller slots by splitting a larger slot.
     *
     * Takes a slot and splits it into smaller chunks of the specified duration.
     * Useful for offering sub-slots within a larger available window.
     *
     * @param  SlotVO  $slot  The slot to split
     * @param  int  $chunkDuration  The duration of each chunk in minutes
     * @return SlotVOCollection Collection of smaller slots
     */
    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration): SlotVOCollection;
}
