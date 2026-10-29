<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Services;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use AndyDefer\PhpVo\ValueObjects\TimeZuluVO;
use Illuminate\Support\Collection;

/**
 * Service providing helper methods for validation rules.
 *
 * @internal This service is intended for internal use by validation rules only.
 */
final class ValidationHelperService
{
    public function timeSlotsOverlap(
        TimeZuluVO $start1,
        TimeZuluVO $end1,
        TimeZuluVO $start2,
        TimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    public function dateRangesOverlap(
        DateTimeZuluVO $start1,
        DateTimeZuluVO $end1,
        DateTimeZuluVO $start2,
        DateTimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * @return array<string>
     */
    public function getDays(AvailabilityRecord|Availability $availability): array
    {
        $days = $availability->days ?? null;

        if ($days === null) {
            return [];
        }

        if ($days instanceof WeekDayCollection) {
            return $days->toStrings();
        }

        if (is_array($days)) {
            return $days;
        }

        return [];
    }

    public function getValidityStart(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_start;
        }

        return $availability->getValidityStart();
    }

    public function getValidityEnd(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_end;
        }

        return $availability->getValidityEnd();
    }

    public function getDailyStart(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_start;
        }

        return $availability->getDailyStart();
    }

    public function getDailyEnd(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_end;
        }

        return $availability->getDailyEnd();
    }

    public function isWithinValidityPeriod(
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
     * @param  array<string>  $days
     */
    public function isDayInArray(WeekDay $day, array $days): bool
    {
        return in_array($day->value, $days, true);
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getConflictingSchedules(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null
    ): Collection {
        $query = Schedule::where('availability_id', $availabilityId)
            ->where(function ($query) use ($start, $end): void {
                $query->where('start_datetime', '<', $end->toDateTimeString())
                    ->where('end_datetime', '>', $start->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Impediment>
     */
    public function getConflictingImpediments(
        int|string $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $excludeId = null
    ): Collection {
        $query = Impediment::where('availability_id', $availabilityId)
            ->where(function ($query) use ($start, $end): void {
                $query->where('start_datetime', '<', $end->toDateTimeString())
                    ->where('end_datetime', '>', $start->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function getNextSchedule(int|string $availabilityId, DateTimeZuluVO $after): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '>', $after->toDateTimeString())
            ->orderBy('start_datetime', 'asc')
            ->first();
    }

    public function getPreviousSchedule(int|string $availabilityId, DateTimeZuluVO $before): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $before->toDateTimeString())
            ->orderBy('start_datetime', 'desc')
            ->first();
    }

    public function getDurationInMinutes(DateTimeZuluVO $start, DateTimeZuluVO $end): int
    {
        return (int) $start->diffInMinutes($end);
    }

    public function getTimeDurationInMinutes(TimeZuluVO $start, TimeZuluVO $end): int
    {
        return $start->diffInMinutes($end);
    }

    public function isWithinDailyBounds(
        DateTimeZuluVO $dateTime,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd
    ): bool {
        $time = TimeZuluVO::from($dateTime->toTimeString());

        if ($dailyStart->isBefore($dailyEnd)) {
            return $time->isBetween($dailyStart, $dailyEnd);
        }

        return $time->isBetween($dailyStart, $dailyEnd);
    }

    /**
     * @return array<string>
     */
    public function getDaysInRange(DateTimeZuluVO $start, DateTimeZuluVO $end): array
    {
        $days = [];
        $current = $start->startOfDay();

        while ($current->isBefore($end) || $current->isEqual($end)) {
            $dayName = strtolower($current->format('l'));

            if (! in_array($dayName, $days, true)) {
                $days[] = $dayName;
            }

            $current = $current->addDays(1);
        }

        return $days;
    }

    /**
     * @param  array<string>  $rangeDays
     * @param  array<string>  $allowedDays
     */
    public function allDaysInRangeAreAllowed(array $rangeDays, array $allowedDays): bool
    {
        foreach ($rangeDays as $day) {
            if (! in_array($day, $allowedDays, true)) {
                return false;
            }
        }

        return true;
    }
}
