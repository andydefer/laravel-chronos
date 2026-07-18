<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use InvalidArgumentException;

/**
 * Immutable Value Object representing a time of day (hours, minutes, seconds) in UTC.
 *
 * All operations are performed in UTC.
 *
 * Supports multiple input formats:
 * - Time string: `14:30:00`, `14:30`
 * - ISO 8601 time: `14:30:00Z`
 * - Seconds since midnight: `52200` (14:30:00)
 * - Array: `[14, 30, 0]`
 *
 * @example
 * $time = TimeZuluVO::from('14:30:00');
 * echo $time->getValue(); // '14:30:00'
 *
 * $newTime = $time->addHours(2);
 * echo $newTime->toTimeString(); // '16:30:00'
 */
final class TimeZuluVO extends AbstractValueObject
{
    private const TIME_FORMAT = 'H:i:s';

    private CarbonInterface $carbon;

    /**
     * Creates a new TimeZuluVO instance in UTC.
     *
     * @param  string|null  $value  Time string (e.g., '14:30:00'), or null for current UTC time
     *
     * @throws InvalidArgumentException If the time string is invalid
     */
    public function __construct(?string $value = null)
    {
        $this->carbon = $this->parseTime($value ?? Carbon::now('UTC')->format(self::TIME_FORMAT));
    }

    /**
     * Parses a time string into a Carbon instance.
     *
     * @param  string  $value  Time string to parse
     * @return CarbonInterface The parsed Carbon instance
     *
     * @throws InvalidArgumentException If the time string is invalid
     */
    private function parseTime(string $value): CarbonInterface
    {
        // Try H:i:s format
        try {
            $carbon = Carbon::createFromFormat(self::TIME_FORMAT, $value);
            if ($carbon !== false) {
                return $carbon->utc();
            }
        } catch (\Exception) {
            // Continue to next parser
        }

        // Try H:i format
        try {
            $carbon = Carbon::createFromFormat('H:i', $value);
            if ($carbon !== false) {
                return $carbon->utc();
            }
        } catch (\Exception) {
            // Continue to next parser
        }

        // Try ISO 8601 time format: H:i:sZ
        if (str_ends_with($value, 'Z')) {
            try {
                $carbon = Carbon::createFromFormat('H:i:s\Z', $value, 'UTC');
                if ($carbon !== false) {
                    return $carbon;
                }
            } catch (\Exception) {
                // Continue to next parser
            }
        }

        // Try seconds since midnight (0-86399)
        if (is_numeric($value)) {
            $seconds = (int) $value;
            if ($seconds >= 0 && $seconds <= 86399) {
                try {
                    $hours = intdiv($seconds, 3600);
                    $minutes = intdiv($seconds % 3600, 60);
                    $secs = $seconds % 60;

                    $carbon = Carbon::createFromTime($hours, $minutes, $secs, 'UTC');
                    if ($carbon !== false) {
                        return $carbon;
                    }
                } catch (\Exception) {
                    // Continue to next parser
                }
            }
        }

        // Try array format: [hours, minutes, seconds]
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            try {
                $array = json_decode($value, true, 3, JSON_THROW_ON_ERROR);

                if (is_array($array) && count($array) >= 2) {
                    $hours = (int) ($array[0] ?? 0);
                    $minutes = (int) ($array[1] ?? 0);
                    $seconds = (int) ($array[2] ?? 0);

                    if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59 && $seconds >= 0 && $seconds <= 59) {
                        $carbon = Carbon::createFromTime($hours, $minutes, $seconds, 'UTC');
                        if ($carbon !== false) {
                            return $carbon;
                        }
                    }
                }
            } catch (\Exception) {
                // Continue to next parser
            }
        }

        // Generic fallback
        try {
            return Carbon::parse($value, 'UTC')->utc();
        } catch (\Exception) {
            // Continue to next parser
        }

        throw new InvalidArgumentException("Invalid time value: {$value}");
    }

    /**
     * Creates a new instance from a Carbon instance.
     *
     * @param  CarbonInterface  $carbon  The Carbon instance to convert
     * @return self A new TimeZuluVO instance
     */
    public static function fromCarbon(CarbonInterface $carbon): self
    {
        return new self($carbon->utc()->format(self::TIME_FORMAT));
    }

    /**
     * Creates a new instance with the current UTC time.
     *
     * @return self A new TimeZuluVO instance set to the current UTC time
     */
    public static function now(): self
    {
        return new self(null);
    }

    /**
     * Creates a new instance with a specific time.
     *
     * @param  int  $hours  The hour (0-23)
     * @param  int  $minutes  The minute (0-59, default: 0)
     * @param  int  $seconds  The second (0-59, default: 0)
     * @return self A new TimeZuluVO instance
     *
     * @throws InvalidArgumentException If the time values are invalid
     */
    public static function create(int $hours, int $minutes = 0, int $seconds = 0): self
    {
        if ($hours < 0 || $hours > 23) {
            throw new InvalidArgumentException("Invalid hour: {$hours}. Must be between 0 and 23.");
        }

        if ($minutes < 0 || $minutes > 59) {
            throw new InvalidArgumentException("Invalid minute: {$minutes}. Must be between 0 and 59.");
        }

        if ($seconds < 0 || $seconds > 59) {
            throw new InvalidArgumentException("Invalid second: {$seconds}. Must be between 0 and 59.");
        }

        $carbon = Carbon::createFromTime($hours, $minutes, $seconds, 'UTC');

        if ($carbon === false) {
            throw new InvalidArgumentException("Invalid time: {$hours}:{$minutes}:{$seconds}");
        }

        return self::fromCarbon($carbon);
    }

    /**
     * Creates a new instance from seconds since midnight.
     *
     * @param  int  $seconds  Seconds since midnight (0-86399)
     * @return self A new TimeZuluVO instance
     *
     * @throws InvalidArgumentException If the seconds value is invalid
     */
    public static function fromSeconds(int $seconds): self
    {
        if ($seconds < 0 || $seconds > 86399) {
            throw new InvalidArgumentException("Invalid seconds: {$seconds}. Must be between 0 and 86399.");
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return self::create($hours, $minutes, $secs);
    }

    /**
     * Returns the underlying Carbon instance.
     *
     * @return CarbonInterface The internal Carbon instance
     */
    public function getCarbon(): CarbonInterface
    {
        return $this->carbon;
    }

    /**
     * Gets the time string representation.
     *
     * @return string The time string in H:i:s format
     */
    public function getValue(): string
    {
        return $this->carbon->format(self::TIME_FORMAT);
    }

    /**
     * Converts to a native DateTime instance.
     *
     * @return DateTime The DateTime representation
     */
    public function toDateTime(): DateTime
    {
        return $this->carbon->toDateTime();
    }

    /**
     * Gets the time part: H:i:s.
     *
     * @return string The time in H:i:s format
     */
    public function toTimeString(): string
    {
        return $this->carbon->format(self::TIME_FORMAT);
    }

    public function __toString(): string
    {
        return $this->toTimeString();
    }

    /**
     * Formats the time using a custom format.
     *
     * @param  string  $format  PHP time format (e.g., 'H:i', 'g:i A')
     * @return string The formatted time string
     */
    public function format(string $format): string
    {
        return $this->carbon->format($format);
    }

    /**
     * Gets the hours component (0-23).
     *
     * @return int The hours
     */
    public function getHours(): int
    {
        return $this->carbon->hour;
    }

    /**
     * Gets the minutes component (0-59).
     *
     * @return int The minutes
     */
    public function getMinutes(): int
    {
        return $this->carbon->minute;
    }

    /**
     * Gets the seconds component (0-59).
     *
     * @return int The seconds
     */
    public function getSeconds(): int
    {
        return $this->carbon->second;
    }

    /**
     * Gets the total seconds since midnight (0-86399) as float.
     *
     * @return float The total seconds since midnight
     */
    public function getTotalSeconds(): float
    {
        return $this->carbon->secondsSinceMidnight();
    }

    /**
     * Check if this time is after another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if this time is after the other
     */
    public function isAfter(self $other): bool
    {
        return $this->getTotalSeconds() > $other->getTotalSeconds();
    }

    /**
     * Check if this time is before another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if this time is before the other
     */
    public function isBefore(self $other): bool
    {
        return $this->getTotalSeconds() < $other->getTotalSeconds();
    }

    /**
     * Check if this time is equal to another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if the times are equal
     */
    public function isEqual(self $other): bool
    {
        return $this->getTotalSeconds() === $other->getTotalSeconds();
    }

    /**
     * Check if this time is between two times.
     *
     * @param  self  $start  The start time
     * @param  self  $end  The end time
     * @param  bool  $includeStart  Whether to include the start time (default: true)
     * @param  bool  $includeEnd  Whether to include the end time (default: true)
     * @return bool True if this time is between the start and end
     */
    public function isBetween(self $start, self $end, bool $includeStart = true, bool $includeEnd = true): bool
    {
        $seconds = (float) $this->getTotalSeconds();
        $startSeconds = (float) $start->getTotalSeconds();
        $endSeconds = (float) $end->getTotalSeconds();

        if ($startSeconds <= $endSeconds) {
            if ($includeStart && $includeEnd) {
                return $seconds >= $startSeconds && $seconds <= $endSeconds;
            }

            if ($includeStart) {
                return $seconds >= $startSeconds && $seconds < $endSeconds;
            }

            if ($includeEnd) {
                return $seconds > $startSeconds && $seconds <= $endSeconds;
            }

            return $seconds > $startSeconds && $seconds < $endSeconds;
        }

        if ($includeStart && $includeEnd) {
            return $seconds >= $startSeconds || $seconds <= $endSeconds;
        }

        if ($includeStart) {
            return $seconds >= $startSeconds || $seconds < $endSeconds;
        }

        if ($includeEnd) {
            return $seconds > $startSeconds || $seconds <= $endSeconds;
        }

        return $seconds > $startSeconds || $seconds < $endSeconds;
    }

    /**
     * Check if this time is on the same hour as another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if both times have the same hour
     */
    public function isSameHour(self $other): bool
    {
        return $this->getHours() === $other->getHours();
    }

    /**
     * Adds a specified number of hours.
     *
     * @param  int  $hours  Number of hours to add
     * @return self A new TimeZuluVO instance
     */
    public function addHours(int $hours): self
    {
        $newCarbon = $this->carbon->copy()->addHours($hours);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Subtracts a specified number of hours.
     *
     * @param  int  $hours  Number of hours to subtract
     * @return self A new TimeZuluVO instance
     */
    public function subHours(int $hours): self
    {
        $newCarbon = $this->carbon->copy()->subHours($hours);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Adds a specified number of minutes.
     *
     * @param  int  $minutes  Number of minutes to add
     * @return self A new TimeZuluVO instance
     */
    public function addMinutes(int $minutes): self
    {
        $newCarbon = $this->carbon->copy()->addMinutes($minutes);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Subtracts a specified number of minutes.
     *
     * @param  int  $minutes  Number of minutes to subtract
     * @return self A new TimeZuluVO instance
     */
    public function subMinutes(int $minutes): self
    {
        $newCarbon = $this->carbon->copy()->subMinutes($minutes);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Adds a specified number of seconds.
     *
     * @param  int  $seconds  Number of seconds to add
     * @return self A new TimeZuluVO instance
     */
    public function addSeconds(int $seconds): self
    {
        $newCarbon = $this->carbon->copy()->addSeconds($seconds);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Subtracts a specified number of seconds.
     *
     * @param  int  $seconds  Number of seconds to subtract
     * @return self A new TimeZuluVO instance
     */
    public function subSeconds(int $seconds): self
    {
        $newCarbon = $this->carbon->copy()->subSeconds($seconds);

        return self::fromCarbon($newCarbon);
    }

    /**
     * Returns the absolute difference in seconds between two times.
     *
     * @param  self  $other  The time to compare against
     * @return float The difference in seconds (always positive)
     */
    public function diffInSeconds(self $other): float
    {
        return abs($this->getTotalSeconds() - $other->getTotalSeconds());
    }

    /**
     * Returns the absolute difference in minutes between two times.
     *
     * @param  self  $other  The time to compare against
     * @return int The difference in minutes (always positive)
     */
    public function diffInMinutes(self $other): int
    {
        return (int) floor(abs($this->getTotalSeconds() - $other->getTotalSeconds()) / 60);
    }

    /**
     * Returns the absolute difference in hours between two times.
     *
     * @param  self  $other  The time to compare against
     * @return int The difference in hours (always positive)
     */
    public function diffInHours(self $other): int
    {
        return (int) floor(abs($this->getTotalSeconds() - $other->getTotalSeconds()) / 3600);
    }

    /**
     * Checks if the time is midnight (00:00:00).
     *
     * @return bool True if the time is midnight
     */
    public function isMidnight(): bool
    {
        return $this->getTotalSeconds() == 0.0;
    }

    /**
     * Checks if the time is noon (12:00:00).
     *
     * @return bool True if the time is noon
     */
    public function isNoon(): bool
    {
        return $this->getHours() === 12 && $this->getMinutes() === 0 && $this->getSeconds() === 0;
    }

    /**
     * Checks if the time is in the morning (00:00:00 to 11:59:59).
     *
     * @return bool True if the time is in the morning
     */
    public function isMorning(): bool
    {
        return $this->getHours() < 12;
    }

    /**
     * Checks if the time is in the afternoon (12:00:00 to 17:59:59).
     *
     * @return bool True if the time is in the afternoon
     */
    public function isAfternoon(): bool
    {
        $hours = $this->getHours();

        return $hours >= 12 && $hours < 18;
    }

    /**
     * Checks if the time is in the evening (18:00:00 to 23:59:59).
     *
     * @return bool True if the time is in the evening
     */
    public function isEvening(): bool
    {
        return $this->getHours() >= 18;
    }

    /**
     * Check if this time is after or equal to another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if this time is after or equal to the other
     */
    public function isAfterOrEqual(self $other): bool
    {
        return $this->getTotalSeconds() >= $other->getTotalSeconds();
    }

    /**
     * Check if this time is before or equal to another time.
     *
     * @param  self  $other  The time to compare against
     * @return bool True if this time is before or equal to the other
     */
    public function isBeforeOrEqual(self $other): bool
    {
        return $this->getTotalSeconds() <= $other->getTotalSeconds();
    }

    /**
     * Compares this time with another time.
     *
     * @param  self  $other  The time to compare against
     * @return int -1 if this time is before, 0 if equal, 1 if after
     */
    public function compareTo(self $other): int
    {
        return $this->getTotalSeconds() <=> $other->getTotalSeconds();
    }
}
