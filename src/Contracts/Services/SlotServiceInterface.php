<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use Illuminate\Support\Collection;

/**
 * Interface for Slot service operations.
 *
 * Provides high-level business operations for finding and generating
 * available time slots within availabilities, considering schedules
 * and impediments as blockers.
 */
interface SlotServiceInterface
{
    /**
     * Find the next available slot after a given time.
     *
     * @param  string  $schedulableType  The entity type (e.g., 'App\Models\User')
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration of the slot
     * @param  int|null  $availabilityId  Optional specific availability to search in
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
     * Find the previous available slot before a given time.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $before  The time before which to search
     * @param  int  $durationInMinutes  The required duration of the slot
     * @param  int|null  $availabilityId  Optional specific availability to search in
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
     * Find all available slots within a date range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the search range
     * @param  DateTimeZuluVO  $end  The end of the search range
     * @param  int  $durationInMinutes  The required duration of each slot
     * @param  int|null  $availabilityId  Optional specific availability to search in
     * @return SlotVOCollection Collection of available slots
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
     * Find all available slots for a specific day.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $date  The date to search
     * @param  int  $durationInMinutes  The required duration of each slot
     * @param  int|null  $availabilityId  Optional specific availability to search in
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
     * Check if a specific time slot is available.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the slot to check
     * @param  DateTimeZuluVO  $end  The end of the slot to check
     * @param  int|null  $availabilityId  Optional specific availability to check
     * @return bool True if the slot is available
     */
    public function isSlotAvailable(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool;

    /**
     * Get the next available start time for a given duration.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $after  The time after which to search
     * @param  int  $durationInMinutes  The required duration
     * @param  int|null  $availabilityId  Optional specific availability to search in
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
     * Check if a schedulable entity has any availability for a given date.
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
     * Get all blocked time periods for a given date range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $availabilityId  Optional specific availability
     * @return array<array{start: DateTimeZuluVO, end: DateTimeZuluVO, type: string}> Blocked periods
     */
    public function getBlockedPeriods(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): array;

    /**
     * Generate slots by splitting a larger slot into smaller ones.
     *
     * @param  SlotVO  $slot  The slot to split
     * @param  int  $chunkDuration  The duration of each chunk
     * @return SlotVOCollection Collection of smaller slots
     */
    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration): SlotVOCollection;
}
