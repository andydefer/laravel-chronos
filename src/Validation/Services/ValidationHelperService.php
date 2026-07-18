<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Services;

use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Support\Collection;

/**
 * Service providing helper methods for validation rules.
 * Injected via composition into validation rules.
 */
final class ValidationHelperService
{
    /**
     * Check if two time slots overlap.
     */
    public function timeSlotsOverlap(TimeZuluVO $start1, TimeZuluVO $end1, TimeZuluVO $start2, TimeZuluVO $end2): bool
    {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Check if two date ranges overlap.
     */
    public function dateRangesOverlap(DateTimeZuluVO $start1, DateTimeZuluVO $end1, DateTimeZuluVO $start2, DateTimeZuluVO $end2): bool
    {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Get days array from availability record or model.
     *
     * @return array<string>
     */
    public function getDays(AvailabilityRecord|Availability $availability): array
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->days ?? [];
        }

        return $availability->days ?? [];
    }

    /**
     * Get validity start from availability record or model.
     */
    public function getValidityStart(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_start;
        }

        return $availability->getValidityStart();
    }

    /**
     * Get validity end from availability record or model.
     */
    public function getValidityEnd(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_end;
        }

        return $availability->getValidityEnd();
    }

    /**
     * Get daily start from availability record or model.
     */
    public function getDailyStart(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_start;
        }

        return $availability->getDailyStart();
    }

    /**
     * Get daily end from availability record or model.
     */
    public function getDailyEnd(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_end;
        }

        return $availability->getDailyEnd();
    }

    /**
     * Check if a date is within validity period.
     */
    public function isWithinValidityPeriod(DateTimeZuluVO $date, ?DateTimeZuluVO $validityStart, ?DateTimeZuluVO $validityEnd): bool
    {
        if ($validityStart !== null && $date->isBefore($validityStart)) {
            return false;
        }

        if ($validityEnd !== null && $date->isAfter($validityEnd)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a day is in the days array.
     *
     * @param  array<string>  $days
     */
    public function isDayInArray(WeekDay $day, array $days): bool
    {
        return in_array($day->value, $days, true);
    }

    /**
     * Get conflicting schedules for a time slot.
     */
    public function getConflictingSchedules(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection
    {
        $query = Schedule::where('availability_id', $availabilityId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end->toDateTimeString())
                    ->where('end_datetime', '>', $start->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Get conflicting impediments for a time slot.
     */
    public function getConflictingImpediments(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection
    {
        $query = Impediment::where('availability_id', $availabilityId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end->toDateTimeString())
                    ->where('end_datetime', '>', $start->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Get the next schedule after a given time.
     */
    public function getNextSchedule(int $availabilityId, DateTimeZuluVO $after): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '>', $after->toDateTimeString())
            ->orderBy('start_datetime', 'asc')
            ->first();
    }

    /**
     * Get the previous schedule before a given time.
     */
    public function getPreviousSchedule(int $availabilityId, DateTimeZuluVO $before): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $before->toDateTimeString())
            ->orderBy('start_datetime', 'desc')
            ->first();
    }

    /**
     * Calculate duration between two times in minutes.
     */
    public function getDurationInMinutes(DateTimeZuluVO $start, DateTimeZuluVO $end): int
    {
        return (int) $start->diffInMinutes($end);
    }

    /**
     * Calculate duration between two times in minutes (TimeZuluVO).
     */
    public function getTimeDurationInMinutes(TimeZuluVO $start, TimeZuluVO $end): int
    {
        return $start->diffInMinutes($end);
    }

    /**
     * Check if a time slot is within daily bounds.
     */
    public function isWithinDailyBounds(DateTimeZuluVO $dateTime, TimeZuluVO $dailyStart, TimeZuluVO $dailyEnd): bool
    {
        $time = TimeZuluVO::from($dateTime->toTimeString());

        if ($dailyStart->isBefore($dailyEnd)) {
            // Normal case: start < end
            return $time->isBetween($dailyStart, $dailyEnd);
        }

        // Cross-day case: start > end (e.g., 22:00 - 06:00)
        return $time->isBetween($dailyStart, $dailyEnd);
    }

    /**
     * Get all days covered by a date range.
     *
     * @return array<string> Day names (monday, tuesday, etc.)
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
     * Check if all days in a range are present in the allowed days.
     *
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
