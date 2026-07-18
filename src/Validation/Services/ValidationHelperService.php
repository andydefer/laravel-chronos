<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Services;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
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
 *
 * This service centralizes common validation logic used across multiple
 * validation rules. It is injected via composition into validation rules
 * to avoid code duplication and promote consistency.
 *
 * @example
 * $helper = new ValidationHelperService();
 * $isOverlap = $helper->timeSlotsOverlap($start1, $end1, $start2, $end2);
 *
 * @internal This service is intended for internal use by validation rules only.
 */
final class ValidationHelperService
{
    /**
     * Checks if two time slots overlap.
     *
     * @param  TimeZuluVO  $start1  Start time of the first slot
     * @param  TimeZuluVO  $end1  End time of the first slot
     * @param  TimeZuluVO  $start2  Start time of the second slot
     * @param  TimeZuluVO  $end2  End time of the second slot
     * @return bool True if the time slots overlap
     */
    public function timeSlotsOverlap(
        TimeZuluVO $start1,
        TimeZuluVO $end1,
        TimeZuluVO $start2,
        TimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Checks if two date ranges overlap.
     *
     * @param  DateTimeZuluVO  $start1  Start date of the first range
     * @param  DateTimeZuluVO  $end1  End date of the first range
     * @param  DateTimeZuluVO  $start2  Start date of the second range
     * @param  DateTimeZuluVO  $end2  End date of the second range
     * @return bool True if the date ranges overlap
     */
    public function dateRangesOverlap(
        DateTimeZuluVO $start1,
        DateTimeZuluVO $end1,
        DateTimeZuluVO $start2,
        DateTimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Gets days array from availability record or model.
     *
     * @param  AvailabilityRecord|Availability  $availability  The availability instance
     * @return array<string> Array of day names
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

    /**
     * Gets validity start from availability record or model.
     *
     * @param  AvailabilityRecord|Availability  $availability  The availability instance
     * @return DateTimeZuluVO|null The validity start date or null
     */
    public function getValidityStart(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_start;
        }

        return $availability->getValidityStart();
    }

    /**
     * Gets validity end from availability record or model.
     *
     * @param  AvailabilityRecord|Availability  $availability  The availability instance
     * @return DateTimeZuluVO|null The validity end date or null
     */
    public function getValidityEnd(AvailabilityRecord|Availability $availability): ?DateTimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->validity_end;
        }

        return $availability->getValidityEnd();
    }

    /**
     * Gets daily start time from availability record or model.
     *
     * @param  AvailabilityRecord|Availability  $availability  The availability instance
     * @return TimeZuluVO|null The daily start time or null
     */
    public function getDailyStart(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_start;
        }

        return $availability->getDailyStart();
    }

    /**
     * Gets daily end time from availability record or model.
     *
     * @param  AvailabilityRecord|Availability  $availability  The availability instance
     * @return TimeZuluVO|null The daily end time or null
     */
    public function getDailyEnd(AvailabilityRecord|Availability $availability): ?TimeZuluVO
    {
        if ($availability instanceof AvailabilityRecord) {
            return $availability->daily_end;
        }

        return $availability->getDailyEnd();
    }

    /**
     * Checks if a date is within the validity period.
     *
     * @param  DateTimeZuluVO  $date  The date to check
     * @param  DateTimeZuluVO|null  $validityStart  The start of the validity period
     * @param  DateTimeZuluVO|null  $validityEnd  The end of the validity period
     * @return bool True if the date is within the validity period
     */
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
     * Checks if a day is present in the days array.
     *
     * @param  WeekDay  $day  The day to check
     * @param  array<string>  $days  Array of day names to search in
     * @return bool True if the day is found in the array
     */
    public function isDayInArray(WeekDay $day, array $days): bool
    {
        return in_array($day->value, $days, true);
    }

    /**
     * Gets conflicting schedules for a time slot.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $start  The start of the time slot
     * @param  DateTimeZuluVO  $end  The end of the time slot
     * @param  int|null  $excludeId  Optional schedule ID to exclude from the query
     * @return Collection<int, Schedule> Collection of conflicting schedules
     */
    public function getConflictingSchedules(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null
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
     * Gets conflicting impediments for a time slot.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $start  The start of the time slot
     * @param  DateTimeZuluVO  $end  The end of the time slot
     * @param  int|null  $excludeId  Optional impediment ID to exclude from the query
     * @return Collection<int, Impediment> Collection of conflicting impediments
     */
    public function getConflictingImpediments(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null
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

    /**
     * Gets the next schedule after a given time.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $after  The time to search after
     * @return Schedule|null The next schedule or null if none exists
     */
    public function getNextSchedule(int $availabilityId, DateTimeZuluVO $after): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '>', $after->toDateTimeString())
            ->orderBy('start_datetime', 'asc')
            ->first();
    }

    /**
     * Gets the previous schedule before a given time.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $before  The time to search before
     * @return Schedule|null The previous schedule or null if none exists
     */
    public function getPreviousSchedule(int $availabilityId, DateTimeZuluVO $before): ?Schedule
    {
        return Schedule::where('availability_id', $availabilityId)
            ->where('start_datetime', '<', $before->toDateTimeString())
            ->orderBy('start_datetime', 'desc')
            ->first();
    }

    /**
     * Calculates duration between two dates in minutes.
     *
     * @param  DateTimeZuluVO  $start  The start date
     * @param  DateTimeZuluVO  $end  The end date
     * @return int The duration in minutes
     */
    public function getDurationInMinutes(DateTimeZuluVO $start, DateTimeZuluVO $end): int
    {
        return (int) $start->diffInMinutes($end);
    }

    /**
     * Calculates duration between two times in minutes.
     *
     * @param  TimeZuluVO  $start  The start time
     * @param  TimeZuluVO  $end  The end time
     * @return int The duration in minutes
     */
    public function getTimeDurationInMinutes(TimeZuluVO $start, TimeZuluVO $end): int
    {
        return $start->diffInMinutes($end);
    }

    /**
     * Checks if a time is within daily bounds.
     *
     * Supports cross-day bounds (e.g., 22:00 to 06:00).
     *
     * @param  DateTimeZuluVO  $dateTime  The date to check
     * @param  TimeZuluVO  $dailyStart  The daily start time
     * @param  TimeZuluVO  $dailyEnd  The daily end time
     * @return bool True if the time is within the bounds
     */
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
     * Gets all days covered by a date range.
     *
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @return array<string> Array of day names (e.g., 'monday', 'tuesday')
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
     * Checks if all days in a range are allowed.
     *
     * @param  array<string>  $rangeDays  Array of days in the range
     * @param  array<string>  $allowedDays  Array of allowed days
     * @return bool True if all days are allowed
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
