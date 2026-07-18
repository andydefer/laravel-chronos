<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Service for finding and generating available time slots.
 *
 * This service implements the core scheduling logic, finding available
 * time slots within availability windows while considering schedules
 * and impediments as blockers. It performs complex date calculations
 * and handles time zone conversions.
 *
 * @example
 * $service = new SlotService($availabilityService, $scheduleService, $impedimentService, $config);
 * $slots = $service->findSlotsForDay('user', 1, today(), 30);
 *
 * @see SlotServiceInterface
 */
final class SlotService implements SlotServiceInterface
{
    private const DEFAULT_SEARCH_DAYS = 30;

    /**
     * @param  AvailabilityServiceInterface  $availabilityService  Service for availability data
     * @param  ScheduleServiceInterface  $scheduleService  Service for schedule data
     * @param  ImpedimentServiceInterface  $impedimentService  Service for impediment data
     * @param  ChronosConfigInterface  $config  Configuration for validation
     */
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly ScheduleServiceInterface $scheduleService,
        private readonly ImpedimentServiceInterface $impedimentService,
        private readonly ChronosConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findNextSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $after, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchEnd = $after->addDays(self::DEFAULT_SEARCH_DAYS);

                $slots = $this->findSlotsInRange(
                    $schedulableType,
                    $schedulableId,
                    $after,
                    $searchEnd,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slots->firstSlot();
            },
            [
                'operation' => 'findNextSlot',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'after' => $after->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findPreviousSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $before,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $before, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchStart = $before->subDays(self::DEFAULT_SEARCH_DAYS);

                $slots = $this->findSlotsInRange(
                    $schedulableType,
                    $schedulableId,
                    $searchStart,
                    $before,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slots->lastSlot();
            },
            [
                'operation' => 'findPreviousSlot',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'before' => $before->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findSlotsInRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $start, $end, $durationInMinutes, $availabilityId): SlotVOCollection {
                $availabilities = $this->fetchAvailabilitiesInRange(
                    $schedulableType,
                    $schedulableId,
                    $start,
                    $end,
                    $availabilityId
                );

                $blockedPeriods = $this->getBlockedPeriods(
                    $schedulableType,
                    $schedulableId,
                    $start,
                    $end,
                    $availabilityId
                );

                $slots = new SlotVOCollection;

                foreach ($availabilities as $availability) {
                    $availabilitySlots = $this->generateSlotsForAvailability(
                        $availability,
                        $start,
                        $end,
                        $durationInMinutes,
                        $blockedPeriods
                    );

                    foreach ($availabilitySlots as $slot) {
                        $slots->add($slot);
                    }
                }

                return $slots->sortByStart();
            },
            [
                'operation' => 'findSlotsInRange',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findSlotsForDay(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $date, $durationInMinutes, $availabilityId): SlotVOCollection {
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                return $this->findSlotsInRange(
                    $schedulableType,
                    $schedulableId,
                    $start,
                    $end,
                    $durationInMinutes,
                    $availabilityId
                );
            },
            [
                'operation' => 'findSlotsForDay',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'date' => $date->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isSlotAvailable(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool {
        $durationInMinutes = (int) $start->diffInMinutes($end);
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $start, $end, $durationInMinutes, $availabilityId): bool {
                $slots = $this->findSlotsInRange(
                    $schedulableType,
                    $schedulableId,
                    $start,
                    $end,
                    $durationInMinutes,
                    $availabilityId
                );

                $matchingSlots = $slots->filter(
                    fn (SlotVO $slot): bool => $slot->getStart()->isEqual($start) &&
                        $slot->getEnd()->isEqual($end)
                );

                return $matchingSlots->count() > 0;
            },
            [
                'operation' => 'isSlotAvailable',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getNextAvailableStart(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?DateTimeZuluVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $after, $durationInMinutes, $availabilityId): ?DateTimeZuluVO {
                $slot = $this->findNextSlot(
                    $schedulableType,
                    $schedulableId,
                    $after,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slot?->getStart();
            },
            [
                'operation' => 'getNextAvailableStart',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'after' => $after->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function hasAvailabilityOnDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): bool {
        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $date): bool {
                $availabilities = $this->availabilityService->findActiveAtDate(
                    $schedulableType,
                    $schedulableId,
                    $date
                );

                return $availabilities->isNotEmpty();
            },
            [
                'operation' => 'hasAvailabilityOnDate',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'date' => $date->toDateTimeString(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockedPeriods(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): array {
        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulableType, $schedulableId, $start, $end, $availabilityId): array {
                $blockedPeriods = [];

                $scheduleBlockers = $this->fetchScheduleBlockers(
                    $schedulableType,
                    $schedulableId,
                    $availabilityId,
                    $start,
                    $end
                );

                $blockedPeriods = array_merge($blockedPeriods, $scheduleBlockers);

                $impedimentBlockers = $this->fetchImpedimentBlockers(
                    $schedulableType,
                    $schedulableId,
                    $availabilityId,
                    $start,
                    $end
                );

                $blockedPeriods = array_merge($blockedPeriods, $impedimentBlockers);

                $this->sortBlockedPeriods($blockedPeriods);

                return $blockedPeriods;
            },
            [
                'operation' => 'getBlockedPeriods',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration): SlotVOCollection
    {
        $this->validateDuration($chunkDuration);

        return ServiceContext::within(
            SlotService::class,
            function () use ($slot, $chunkDuration): SlotVOCollection {
                $chunks = $slot->split($chunkDuration);

                $collection = new SlotVOCollection;
                foreach ($chunks as $chunk) {
                    $collection->add($chunk);
                }

                return $collection;
            },
            [
                'operation' => 'generateSlotsFromSlot',
                'slot_start' => $slot->getStart()->toDateTimeString(),
                'slot_end' => $slot->getEnd()->toDateTimeString(),
                'chunk_duration' => $chunkDuration,
            ]
        );
    }

    /**
     * Validates that the duration meets the minimum required duration.
     *
     * @param  int  $durationInMinutes  The duration to validate
     *
     * @throws InvalidArgumentException When the duration is too short
     */
    private function validateDuration(int $durationInMinutes): void
    {
        $minDuration = $this->config->getMinSlotSearchDuration();

        if ($durationInMinutes < $minDuration) {
            throw new InvalidArgumentException(
                sprintf(
                    'Duration (%d minutes) is too short. Minimum allowed duration for slot search is %d minutes.',
                    $durationInMinutes,
                    $minDuration
                )
            );
        }
    }

    /**
     * Fetches availabilities within the specified range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $availabilityId  Optional specific availability
     * @return Collection<int, Availability> Collection of availabilities
     */
    private function fetchAvailabilitiesInRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection {
        if ($availabilityId !== null) {
            $availability = $this->availabilityService->find($availabilityId);

            return $availability !== null
                ? new Collection([$availability])
                : new Collection;
        }

        return $this->availabilityService->findActiveInDateRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end
        );
    }

    /**
     * Fetches schedule blockers within the range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  int|null  $availabilityId  Optional availability filter
     * @param  DateTimeZuluVO  $rangeStart  The start of the range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the range
     * @return array<array{start: DateTimeZuluVO, end: DateTimeZuluVO, type: string, id: int}>
     *                                                                                         Array of schedule blockers
     */
    private function fetchScheduleBlockers(
        string $schedulableType,
        int $schedulableId,
        ?int $availabilityId,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): array {
        $blockers = [];

        $schedules = $availabilityId !== null
            ? $this->scheduleService->findByAvailability($availabilityId)
            : $this->scheduleService->findBySchedulable($schedulableType, $schedulableId);

        foreach ($schedules as $schedule) {
            if ($schedule->start_datetime && $schedule->end_datetime) {
                $scheduleStart = DateTimeZuluVO::fromCarbon($schedule->start_datetime);
                $scheduleEnd = DateTimeZuluVO::fromCarbon($schedule->end_datetime);

                if ($this->doTimeRangesOverlap($scheduleStart, $scheduleEnd, $rangeStart, $rangeEnd)) {
                    $blockers[] = [
                        'start' => $scheduleStart,
                        'end' => $scheduleEnd,
                        'type' => 'schedule',
                        'id' => $schedule->id,
                    ];
                }
            }
        }

        return $blockers;
    }

    /**
     * Fetches impediment blockers within the range.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @param  int|null  $availabilityId  Optional availability filter
     * @param  DateTimeZuluVO  $rangeStart  The start of the range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the range
     * @return array<array{start: DateTimeZuluVO, end: DateTimeZuluVO, type: string, id: int}>
     *                                                                                         Array of impediment blockers
     */
    private function fetchImpedimentBlockers(
        string $schedulableType,
        int $schedulableId,
        ?int $availabilityId,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): array {
        $blockers = [];

        $impediments = $availabilityId !== null
            ? $this->impedimentService->findByAvailability($availabilityId)
            : $this->impedimentService->findBySchedulable($schedulableType, $schedulableId);

        foreach ($impediments as $impediment) {
            if ($impediment->start_datetime && $impediment->end_datetime) {
                $impedimentStart = DateTimeZuluVO::fromCarbon($impediment->start_datetime);
                $impedimentEnd = DateTimeZuluVO::fromCarbon($impediment->end_datetime);

                if ($this->doTimeRangesOverlap($impedimentStart, $impedimentEnd, $rangeStart, $rangeEnd)) {
                    $blockers[] = [
                        'start' => $impedimentStart,
                        'end' => $impedimentEnd,
                        'type' => 'impediment',
                        'id' => $impediment->id,
                    ];
                }
            }
        }

        return $blockers;
    }

    /**
     * Sorts blocked periods by start time.
     *
     * @param  array  $blockedPeriods  The blocked periods to sort (modified by reference)
     */
    private function sortBlockedPeriods(array &$blockedPeriods): void
    {
        usort($blockedPeriods, function (array $a, array $b): int {
            return $a['start']->diffInSeconds($b['start']) <=> 0;
        });
    }

    /**
     * Checks if two time ranges overlap.
     *
     * @param  DateTimeZuluVO  $start1  Start of first range
     * @param  DateTimeZuluVO  $end1  End of first range
     * @param  DateTimeZuluVO  $start2  Start of second range
     * @param  DateTimeZuluVO  $end2  End of second range
     * @return bool True if the ranges overlap
     */
    private function doTimeRangesOverlap(
        DateTimeZuluVO $start1,
        DateTimeZuluVO $end1,
        DateTimeZuluVO $start2,
        DateTimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Generates all slots for a single availability within the range.
     *
     * @param  Availability  $availability  The availability to process
     * @param  DateTimeZuluVO  $rangeStart  The start of the search range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the search range
     * @param  int  $durationInMinutes  The required duration in minutes
     * @param  array  $blockedPeriods  All blocked periods in the range
     * @return array<SlotVO> Array of generated slots
     */
    private function generateSlotsForAvailability(
        Availability $availability,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd,
        int $durationInMinutes,
        array $blockedPeriods
    ): array {
        $dailyStart = $availability->getDailyStart();
        $dailyEnd = $availability->getDailyEnd();
        $days = $availability->getDays();

        if ($dailyStart === null || $dailyEnd === null || $days->isEmpty()) {
            return [];
        }

        $validityStart = $availability->getValidityStart();
        $validityEnd = $availability->getValidityEnd();

        $slots = [];
        $currentDate = $rangeStart->startOfDay();

        while ($currentDate->isBeforeOrEqual($rangeEnd)) {
            $dayName = strtolower($currentDate->format('l'));

            if ($this->isDayAllowed($dayName, $days) &&
                $this->isWithinValidityPeriod($currentDate, $validityStart, $validityEnd)
            ) {
                $daySlots = $this->generateSlotsForDay(
                    $currentDate,
                    $dailyStart,
                    $dailyEnd,
                    $durationInMinutes,
                    $blockedPeriods,
                    $rangeStart,
                    $rangeEnd
                );

                $slots = array_merge($slots, $daySlots);
            }

            $currentDate = $currentDate->addDays(1);
        }

        return $slots;
    }

    /**
     * Checks if a day is allowed by the availability configuration.
     *
     * @param  string  $dayName  The day name (e.g., 'monday')
     * @param  WeekDayCollection  $days  The allowed days collection
     * @return bool True if the day is allowed
     */
    private function isDayAllowed(string $dayName, WeekDayCollection $days): bool
    {
        foreach ($days as $day) {
            if ($day->value === $dayName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a date is within the validity period.
     *
     * @param  DateTimeZuluVO  $date  The date to check
     * @param  DateTimeZuluVO|null  $validityStart  The start of the validity period
     * @param  DateTimeZuluVO|null  $validityEnd  The end of the validity period
     * @return bool True if the date is within the validity period
     */
    private function isWithinValidityPeriod(
        DateTimeZuluVO $date,
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): bool {
        if ($validityStart !== null && $date->isBefore($validityStart)) {
            return false;
        }

        if ($validityEnd !== null && $date->isAfter($validityEnd)) {
            return false;
        }

        return true;
    }

    /**
     * Generates slots for a single day within the search range.
     *
     * @param  DateTimeZuluVO  $date  The day to process
     * @param  TimeZuluVO  $dailyStart  The daily start time
     * @param  TimeZuluVO  $dailyEnd  The daily end time
     * @param  int  $durationInMinutes  The required duration in minutes
     * @param  array  $blockedPeriods  All blocked periods in the range
     * @param  DateTimeZuluVO  $rangeStart  The start of the search range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the search range
     * @return array<SlotVO> Array of generated slots
     */
    private function generateSlotsForDay(
        DateTimeZuluVO $date,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd,
        int $durationInMinutes,
        array $blockedPeriods,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): array {
        $dayStart = DateTimeZuluVO::from(
            $date->toDateString().'T'.$dailyStart->toTimeString().'Z'
        );

        $dayEnd = DateTimeZuluVO::from(
            $date->toDateString().'T'.$dailyEnd->toTimeString().'Z'
        );

        if ($dailyStart->isAfter($dailyEnd)) {
            $dayEnd = $dayEnd->addDays(1);
        }

        // Clip to search range
        $dayStart = $dayStart->isBefore($rangeStart) ? $rangeStart : $dayStart;
        $dayEnd = $dayEnd->isAfter($rangeEnd) ? $rangeEnd : $dayEnd;

        if ($dayStart->isAfter($dayEnd)) {
            return [];
        }

        $dayBlocked = $this->filterBlockedPeriodsForDay($blockedPeriods, $dayStart, $dayEnd);
        $this->sortBlockedPeriods($dayBlocked);

        return $this->generateSlotsAroundBlockedPeriods(
            $dayStart,
            $dayEnd,
            $dayBlocked,
            $durationInMinutes
        );
    }

    /**
     * Filters blocked periods to only those that overlap with the day.
     *
     * @param  array  $blockedPeriods  All blocked periods
     * @param  DateTimeZuluVO  $dayStart  The start of the day
     * @param  DateTimeZuluVO  $dayEnd  The end of the day
     * @return array Filtered blocked periods
     */
    private function filterBlockedPeriodsForDay(
        array $blockedPeriods,
        DateTimeZuluVO $dayStart,
        DateTimeZuluVO $dayEnd
    ): array {
        return array_filter(
            $blockedPeriods,
            function (array $blocked) use ($dayStart, $dayEnd): bool {
                return $blocked['start']->isBefore($dayEnd) &&
                       $blocked['end']->isAfter($dayStart);
            }
        );
    }

    /**
     * Generates slots in the gaps between blocked periods.
     *
     * @param  DateTimeZuluVO  $dayStart  The start of the day
     * @param  DateTimeZuluVO  $dayEnd  The end of the day
     * @param  array  $blockedPeriods  Blocked periods sorted by start time
     * @param  int  $durationInMinutes  The required duration in minutes
     * @return array<SlotVO> Array of generated slots
     */
    private function generateSlotsAroundBlockedPeriods(
        DateTimeZuluVO $dayStart,
        DateTimeZuluVO $dayEnd,
        array $blockedPeriods,
        int $durationInMinutes
    ): array {
        $slots = [];
        $currentStart = $dayStart;

        foreach ($blockedPeriods as $blocked) {
            $blockStart = $blocked['start']->isBefore($dayStart)
                ? $dayStart
                : $blocked['start'];

            $blockEnd = $blocked['end']->isAfter($dayEnd)
                ? $dayEnd
                : $blocked['end'];

            // Generate slots before this blocked period
            if ($currentStart->isBefore($blockStart)) {
                $slots = array_merge(
                    $slots,
                    $this->generateSlotsInInterval($currentStart, $blockStart, $durationInMinutes)
                );
            }

            // Move past the blocked period
            if ($blockEnd->isAfter($currentStart)) {
                $currentStart = $blockEnd;
            }
        }

        // Generate slots after the last blocked period
        if ($currentStart->isBefore($dayEnd)) {
            $slots = array_merge(
                $slots,
                $this->generateSlotsInInterval($currentStart, $dayEnd, $durationInMinutes)
            );
        }

        return $slots;
    }

    /**
     * Generates slots of fixed duration within an interval.
     *
     * @param  DateTimeZuluVO  $start  The start of the interval
     * @param  DateTimeZuluVO  $end  The end of the interval
     * @param  int  $durationInMinutes  The duration of each slot in minutes
     * @return array<SlotVO> Array of generated slots
     */
    private function generateSlotsInInterval(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes
    ): array {
        $slots = [];
        $current = $start;

        while ($current->addMinutes($durationInMinutes)->isBeforeOrEqual($end)) {
            $slots[] = SlotVO::fromDuration($current, $durationInMinutes);
            $current = $current->addMinutes($durationInMinutes);
        }

        return $slots;
    }
}
