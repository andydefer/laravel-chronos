<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Collections\BlockedPeriodCollection;
use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\BlockedPeriodVO;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Database\Eloquent\Model;
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
 * $slots = $service->findSlotsForDay($user, today(), 30);
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
        Model $schedulable,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $after, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchEnd = $after->addDays(self::DEFAULT_SEARCH_DAYS);

                $slots = $this->findSlotsInRange(
                    $schedulable,
                    $after,
                    $searchEnd,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slots->firstSlot();
            },
            [
                'operation' => 'findNextSlot',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $before,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $before, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchStart = $before->subDays(self::DEFAULT_SEARCH_DAYS);

                $slots = $this->findSlotsInRange(
                    $schedulable,
                    $searchStart,
                    $before,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slots->lastSlot();
            },
            [
                'operation' => 'findPreviousSlot',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $start, $end, $durationInMinutes, $availabilityId): SlotVOCollection {
                $availabilities = $this->fetchAvailabilitiesInRange(
                    $schedulable,
                    $start,
                    $end,
                    $availabilityId
                );

                $blockedPeriods = $this->getBlockedPeriods(
                    $schedulable,
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
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $date,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $date, $durationInMinutes, $availabilityId): SlotVOCollection {
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                return $this->findSlotsInRange(
                    $schedulable,
                    $start,
                    $end,
                    $durationInMinutes,
                    $availabilityId
                );
            },
            [
                'operation' => 'findSlotsForDay',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool {
        $durationInMinutes = (int) $start->diffInMinutes($end);
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $start, $end, $durationInMinutes, $availabilityId): bool {
                $slots = $this->findSlotsInRange(
                    $schedulable,
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
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?DateTimeZuluVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $after, $durationInMinutes, $availabilityId): ?DateTimeZuluVO {
                $slot = $this->findNextSlot(
                    $schedulable,
                    $after,
                    $durationInMinutes,
                    $availabilityId
                );

                return $slot?->getStart();
            },
            [
                'operation' => 'getNextAvailableStart',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
        Model $schedulable,
        DateTimeZuluVO $date
    ): bool {
        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $date): bool {
                $availabilities = $this->availabilityService->findActiveAtDate(
                    $schedulable,
                    $date
                );

                return $availabilities->isNotEmpty();
            },
            [
                'operation' => 'hasAvailabilityOnDate',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'date' => $date->toDateTimeString(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockedPeriods(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): BlockedPeriodCollection {
        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $start, $end, $availabilityId): BlockedPeriodCollection {
                $collection = new BlockedPeriodCollection;

                $scheduleBlockers = $this->fetchScheduleBlockers(
                    $schedulable,
                    $availabilityId,
                    $start,
                    $end
                );

                foreach ($scheduleBlockers as $blocker) {
                    $collection->add($blocker);
                }

                $impedimentBlockers = $this->fetchImpedimentBlockers(
                    $schedulable,
                    $availabilityId,
                    $start,
                    $end
                );

                foreach ($impedimentBlockers as $blocker) {
                    $collection->add($blocker);
                }

                return $collection->sortByStart();
            },
            [
                'operation' => 'getBlockedPeriods',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
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
     * @param  Model  $schedulable  The schedulable entity
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @param  int|null  $availabilityId  Optional specific availability
     * @return Collection<int, Availability> Collection of availabilities
     */
    private function fetchAvailabilitiesInRange(
        Model $schedulable,
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
            $schedulable,
            $start,
            $end
        );
    }

    /**
     * Fetches schedule blockers within the range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  int|null  $availabilityId  Optional availability filter
     * @param  DateTimeZuluVO  $rangeStart  The start of the range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the range
     * @return BlockedPeriodCollection Collection of schedule blockers
     */
    private function fetchScheduleBlockers(
        Model $schedulable,
        ?int $availabilityId,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): BlockedPeriodCollection {
        $collection = new BlockedPeriodCollection;

        $schedules = $availabilityId !== null
            ? $this->scheduleService->findByAvailability($availabilityId)
            : $this->scheduleService->findBySchedulable($schedulable);

        foreach ($schedules as $schedule) {
            if ($schedule->start_datetime && $schedule->end_datetime) {
                $scheduleStart = DateTimeZuluVO::fromCarbon($schedule->start_datetime);
                $scheduleEnd = DateTimeZuluVO::fromCarbon($schedule->end_datetime);

                if ($this->doTimeRangesOverlap($scheduleStart, $scheduleEnd, $rangeStart, $rangeEnd)) {
                    $collection->add(new BlockedPeriodVO(
                        $scheduleStart,
                        $scheduleEnd,
                        'schedule',
                        $schedule->id
                    ));
                }
            }
        }

        return $collection;
    }

    /**
     * Fetches impediment blockers within the range.
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  int|null  $availabilityId  Optional availability filter
     * @param  DateTimeZuluVO  $rangeStart  The start of the range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the range
     * @return BlockedPeriodCollection Collection of impediment blockers
     */
    private function fetchImpedimentBlockers(
        Model $schedulable,
        ?int $availabilityId,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): BlockedPeriodCollection {
        $collection = new BlockedPeriodCollection;

        $impediments = $availabilityId !== null
            ? $this->impedimentService->findByAvailability($availabilityId)
            : $this->impedimentService->findBySchedulable($schedulable);

        foreach ($impediments as $impediment) {
            if ($impediment->start_datetime && $impediment->end_datetime) {
                $impedimentStart = DateTimeZuluVO::fromCarbon($impediment->start_datetime);
                $impedimentEnd = DateTimeZuluVO::fromCarbon($impediment->end_datetime);

                if ($this->doTimeRangesOverlap($impedimentStart, $impedimentEnd, $rangeStart, $rangeEnd)) {
                    $collection->add(new BlockedPeriodVO(
                        $impedimentStart,
                        $impedimentEnd,
                        'impediment',
                        $impediment->id
                    ));
                }
            }
        }

        return $collection;
    }

    /**
     * Sorts blocked periods by start time.
     *
     * @param  BlockedPeriodCollection  $blockedPeriods  The blocked periods to sort
     * @return BlockedPeriodCollection Sorted collection
     */
    private function sortBlockedPeriods(BlockedPeriodCollection $blockedPeriods): BlockedPeriodCollection
    {
        return $blockedPeriods->sortByStart();
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
     * @param  BlockedPeriodCollection  $blockedPeriods  All blocked periods in the range
     * @return SlotVOCollection Collection of generated slots
     */
    private function generateSlotsForAvailability(
        Availability $availability,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd,
        int $durationInMinutes,
        BlockedPeriodCollection $blockedPeriods
    ): SlotVOCollection {
        $dailyStart = $availability->getDailyStart();
        $dailyEnd = $availability->getDailyEnd();
        $days = $availability->getDays();

        if ($dailyStart === null || $dailyEnd === null || $days->isEmpty()) {
            return new SlotVOCollection;
        }

        $validityStart = $availability->getValidityStart();
        $validityEnd = $availability->getValidityEnd();

        $slots = new SlotVOCollection;
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

                foreach ($daySlots as $slot) {
                    $slots->add($slot);
                }
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
     * @param  BlockedPeriodCollection  $blockedPeriods  All blocked periods in the range
     * @param  DateTimeZuluVO  $rangeStart  The start of the search range
     * @param  DateTimeZuluVO  $rangeEnd  The end of the search range
     * @return SlotVOCollection Collection of generated slots
     */
    private function generateSlotsForDay(
        DateTimeZuluVO $date,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd,
        int $durationInMinutes,
        BlockedPeriodCollection $blockedPeriods,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): SlotVOCollection {
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
            return new SlotVOCollection;
        }

        $dayBlocked = $blockedPeriods->filterOverlapping($dayStart, $dayEnd);

        return $this->generateSlotsAroundBlockedPeriods(
            $dayStart,
            $dayEnd,
            $dayBlocked,
            $durationInMinutes
        );
    }

    /**
     * Generates slots in the gaps between blocked periods.
     *
     * @param  DateTimeZuluVO  $dayStart  The start of the day
     * @param  DateTimeZuluVO  $dayEnd  The end of the day
     * @param  BlockedPeriodCollection  $blockedPeriods  Blocked periods sorted by start time
     * @param  int  $durationInMinutes  The required duration in minutes
     * @return SlotVOCollection Collection of generated slots
     */
    private function generateSlotsAroundBlockedPeriods(
        DateTimeZuluVO $dayStart,
        DateTimeZuluVO $dayEnd,
        BlockedPeriodCollection $blockedPeriods,
        int $durationInMinutes
    ): SlotVOCollection {
        $slots = new SlotVOCollection;
        $currentStart = $dayStart;

        foreach ($blockedPeriods as $blocked) {
            $blockStart = $blocked->getStart()->isBefore($dayStart)
                ? $dayStart
                : $blocked->getStart();

            $blockEnd = $blocked->getEnd()->isAfter($dayEnd)
                ? $dayEnd
                : $blocked->getEnd();

            // Generate slots before this blocked period
            if ($currentStart->isBefore($blockStart)) {
                $intervalSlots = $this->generateSlotsInInterval(
                    $currentStart,
                    $blockStart,
                    $durationInMinutes
                );

                foreach ($intervalSlots as $slot) {
                    $slots->add($slot);
                }
            }

            // Move past the blocked period
            if ($blockEnd->isAfter($currentStart)) {
                $currentStart = $blockEnd;
            }
        }

        // Generate slots after the last blocked period
        if ($currentStart->isBefore($dayEnd)) {
            $intervalSlots = $this->generateSlotsInInterval(
                $currentStart,
                $dayEnd,
                $durationInMinutes
            );

            foreach ($intervalSlots as $slot) {
                $slots->add($slot);
            }
        }

        return $slots;
    }

    /**
     * Generates slots of fixed duration within an interval.
     *
     * @param  DateTimeZuluVO  $start  The start of the interval
     * @param  DateTimeZuluVO  $end  The end of the interval
     * @param  int  $durationInMinutes  The duration of each slot in minutes
     * @return SlotVOCollection Collection of generated slots
     */
    private function generateSlotsInInterval(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes
    ): SlotVOCollection {
        $slots = new SlotVOCollection;
        $current = $start;

        while ($current->addMinutes($durationInMinutes)->isBeforeOrEqual($end)) {
            $slots->add(SlotVO::fromDuration($current, $durationInMinutes));
            $current = $current->addMinutes($durationInMinutes);
        }

        return $slots;
    }
}
