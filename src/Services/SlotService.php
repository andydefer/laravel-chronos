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
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SlotService implements SlotServiceInterface
{
    private const WINDOW_MERGE_TOLERANCE_SECONDS = 1;

    private const ISO_DAY_NAMES = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

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
        int|string|null $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $after, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchEnd = $after->addDays($this->config->getDefaultSearchDays());

                $slots = $this->findSlotsInRange(
                    $schedulable,
                    $after,
                    $searchEnd,
                    $durationInMinutes,
                    $availabilityId,
                    1
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
        int|string|null $availabilityId = null
    ): ?SlotVO {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $before, $durationInMinutes, $availabilityId): ?SlotVO {
                $searchStart = $before->subDays($this->config->getDefaultSearchDays());

                $slots = $this->findSlotsInRange(
                    $schedulable,
                    $searchStart,
                    $before,
                    $durationInMinutes,
                    $availabilityId,
                    1
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
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $start, $end, $durationInMinutes, $availabilityId, $limit): SlotVOCollection {
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

                $sortedSlots = $slots->sortByStart();

                if ($limit !== null && $limit > 0) {
                    $limitedSlots = new SlotVOCollection;
                    $count = 0;
                    foreach ($sortedSlots as $slot) {
                        if ($count >= $limit) {
                            break;
                        }
                        $limitedSlots->add($slot);
                        $count++;
                    }

                    return $limitedSlots;
                }

                return $sortedSlots;
            },
            [
                'operation' => 'findSlotsInRange',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
                'limit' => $limit,
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
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): SlotVOCollection {
        $this->validateDuration($durationInMinutes);

        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $date, $durationInMinutes, $availabilityId, $limit): SlotVOCollection {
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                return $this->findSlotsInRange(
                    $schedulable,
                    $start,
                    $end,
                    $durationInMinutes,
                    $availabilityId,
                    $limit
                );
            },
            [
                'operation' => 'findSlotsForDay',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'date' => $date->toDateTimeString(),
                'duration' => $durationInMinutes,
                'availability_id' => $availabilityId,
                'limit' => $limit,
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
        int|string|null $availabilityId = null
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
        int|string|null $availabilityId = null
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
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): BlockedPeriodCollection {
        return ServiceContext::within(
            SlotService::class,
            function () use ($schedulable, $start, $end, $availabilityId, $limit): BlockedPeriodCollection {
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

                $sortedCollection = $collection->sortByStart();

                if ($limit !== null && $limit > 0) {
                    $limitedCollection = new BlockedPeriodCollection;
                    $count = 0;
                    foreach ($sortedCollection as $period) {
                        if ($count >= $limit) {
                            break;
                        }
                        $limitedCollection->add($period);
                        $count++;
                    }

                    return $limitedCollection;
                }

                return $sortedCollection;
            },
            [
                'operation' => 'getBlockedPeriods',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration, ?int $limit = null): SlotVOCollection
    {
        $this->validateDuration($chunkDuration);

        return ServiceContext::within(
            SlotService::class,
            function () use ($slot, $chunkDuration, $limit): SlotVOCollection {
                $chunks = $slot->split($chunkDuration);

                $collection = new SlotVOCollection;
                $count = 0;
                foreach ($chunks as $chunk) {
                    if ($limit !== null && $count >= $limit) {
                        break;
                    }
                    $collection->add($chunk);
                    $count++;
                }

                return $collection;
            },
            [
                'operation' => 'generateSlotsFromSlot',
                'slot_start' => $slot->getStart()->toDateTimeString(),
                'slot_end' => $slot->getEnd()->toDateTimeString(),
                'chunk_duration' => $chunkDuration,
                'limit' => $limit,
            ]
        );
    }

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

    private function fetchAvailabilitiesInRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $availabilityId = null
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

    private function fetchScheduleBlockers(
        Model $schedulable,
        int|string|null $availabilityId,
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

    private function fetchImpedimentBlockers(
        Model $schedulable,
        int|string|null $availabilityId,
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
     * Slots are produced as contiguous blocks across consecutive allowed
     * days so that cross-day bookings (e.g. a 24h slot spanning two
     * calendar days) are represented as a single SlotVO instead of being
     * split at midnight.
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

        $windows = $this->buildContiguousWindows(
            $availability,
            $rangeStart,
            $rangeEnd,
            $validityStart,
            $validityEnd
        );

        $slots = new SlotVOCollection;

        foreach ($windows as $window) {
            [$windowStart, $windowEnd] = $window;

            $windowBlocked = $blockedPeriods->filterOverlapping($windowStart, $windowEnd);

            $windowSlots = $this->generateSlotsAroundBlockedPeriods(
                $windowStart,
                $windowEnd,
                $windowBlocked,
                $durationInMinutes
            );

            foreach ($windowSlots as $slot) {
                $slots->add($slot);
            }
        }

        return $slots;
    }

    /**
     * Builds contiguous availability windows across consecutive days.
     *
     * @return array<int, array{0: DateTimeZuluVO, 1: DateTimeZuluVO}>
     */
    private function buildContiguousWindows(
        Availability $availability,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd,
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): array {
        $dailyStart = $availability->getDailyStart();
        $dailyEnd = $availability->getDailyEnd();
        $days = $availability->getDays();

        if ($dailyStart === null || $dailyEnd === null) {
            return [];
        }

        $isFullDay = $dailyStart->toTimeString() === '00:00:00'
            && $dailyEnd->toTimeString() === '23:59:59';

        $windows = [];

        $currentDate = $rangeStart->startOfDay();
        $pendingStart = null;
        $pendingEnd = null;

        while ($currentDate->isBeforeOrEqual($rangeEnd)) {
            $dayName = $this->resolveDayName($currentDate);

            $allowed = $this->isDayAllowed($dayName, $days)
                && $this->isWithinValidityPeriod($currentDate, $validityStart, $validityEnd);

            if (! $allowed) {
                if ($pendingStart !== null && $pendingEnd !== null) {
                    $windows[] = [$pendingStart, $pendingEnd];
                    $pendingStart = null;
                    $pendingEnd = null;
                }

                $currentDate = $currentDate->addDays(1);

                continue;
            }

            $dayStart = DateTimeZuluVO::from(
                $currentDate->toDateString().'T'.$dailyStart->toTimeString().'Z'
            );

            $dayEnd = DateTimeZuluVO::from(
                $currentDate->toDateString().'T'.$dailyEnd->toTimeString().'Z'
            );

            if ($isFullDay) {
                $dayEnd = $currentDate->addDays(1)->startOfDay();
            } elseif ($dailyStart->isAfter($dailyEnd)) {
                $dayEnd = $dayEnd->addDays(1);
            }

            $clippedStart = $dayStart->isBefore($rangeStart) ? $rangeStart : $dayStart;
            $clippedEnd = $dayEnd->isAfter($rangeEnd) ? $rangeEnd : $dayEnd;

            if ($clippedStart->isAfter($clippedEnd) || $clippedStart->isEqual($clippedEnd)) {
                $currentDate = $currentDate->addDays(1);

                continue;
            }

            if ($pendingStart !== null && $pendingEnd !== null) {
                $toleranceBoundary = DateTimeZuluVO::fromCarbon(
                    $clippedStart->getCarbon()->copy()->subSecond()
                );

                $adjacent = $pendingEnd->isAfterOrEqual($toleranceBoundary);

                if ($adjacent) {
                    $pendingEnd = $pendingEnd->isAfter($clippedEnd) ? $pendingEnd : $clippedEnd;
                } else {
                    $windows[] = [$pendingStart, $pendingEnd];
                    $pendingStart = $clippedStart;
                    $pendingEnd = $clippedEnd;
                }
            } else {
                $pendingStart = $clippedStart;
                $pendingEnd = $clippedEnd;
            }

            $currentDate = $currentDate->addDays(1);
        }

        if ($pendingStart !== null && $pendingEnd !== null) {
            $windows[] = [$pendingStart, $pendingEnd];
        }

        return $windows;
    }

    /**
     * Resolves the lowercase English day name from a DateTimeZuluVO,
     * independent of the locale configured on Carbon.
     */
    private function resolveDayName(DateTimeZuluVO $date): string
    {
        return self::ISO_DAY_NAMES[$date->getDayOfWeek()] ?? '';
    }

    private function isDayAllowed(string $dayName, WeekDayCollection $days): bool
    {
        foreach ($days as $day) {
            if ($day->value === $dayName) {
                return true;
            }
        }

        return false;
    }

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
     * Generates slots in the gaps between blocked periods.
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

            if ($blockEnd->isAfter($currentStart)) {
                $currentStart = $blockEnd;
            }
        }

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
