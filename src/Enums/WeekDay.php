<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Enums;

/**
 * Enum representing days of the week.
 */
enum WeekDay: string
{
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';

    /**
     * Get the human-readable label for the day.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
        };
    }

    /**
     * Get the short label (3 letters) for the day.
     */
    public function getShortLabel(): string
    {
        return match ($this) {
            self::MONDAY => 'Mon',
            self::TUESDAY => 'Tue',
            self::WEDNESDAY => 'Wed',
            self::THURSDAY => 'Thu',
            self::FRIDAY => 'Fri',
            self::SATURDAY => 'Sat',
            self::SUNDAY => 'Sun',
        };
    }

    /**
     * Get the day number (1 = Monday, 7 = Sunday).
     */
    public function getNumber(): int
    {
        return match ($this) {
            self::MONDAY => 1,
            self::TUESDAY => 2,
            self::WEDNESDAY => 3,
            self::THURSDAY => 4,
            self::FRIDAY => 5,
            self::SATURDAY => 6,
            self::SUNDAY => 7,
        };
    }

    /**
     * Check if the day is a weekend day (Saturday or Sunday).
     */
    public function isWeekend(): bool
    {
        return $this === self::SATURDAY || $this === self::SUNDAY;
    }

    /**
     * Check if the day is a weekday (Monday to Friday).
     */
    public function isWeekday(): bool
    {
        return ! $this->isWeekend();
    }

    /**
     * Get all weekdays (Monday to Friday).
     *
     * @return array<self>
     */
    public static function weekdays(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
        ];
    }

    /**
     * Get all weekend days (Saturday and Sunday).
     *
     * @return array<self>
     */
    public static function weekends(): array
    {
        return [
            self::SATURDAY,
            self::SUNDAY,
        ];
    }

    /**
     * Get all days of the week.
     *
     * @return array<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Create from a string (case-insensitive).
     */
    public static function fromString(string $day): ?self
    {
        $day = strtolower(trim($day));

        return match ($day) {
            'monday', 'mon' => self::MONDAY,
            'tuesday', 'tue' => self::TUESDAY,
            'wednesday', 'wed' => self::WEDNESDAY,
            'thursday', 'thu' => self::THURSDAY,
            'friday', 'fri' => self::FRIDAY,
            'saturday', 'sat' => self::SATURDAY,
            'sunday', 'sun' => self::SUNDAY,
            default => null,
        };
    }

    /**
     * Get the next day.
     */
    public function next(): self
    {
        $number = $this->getNumber();

        if ($number === 7) {
            return self::MONDAY;
        }

        return self::fromNumber($number + 1);
    }

    /**
     * Get the previous day.
     */
    public function previous(): self
    {
        $number = $this->getNumber();

        if ($number === 1) {
            return self::SUNDAY;
        }

        return self::fromNumber($number - 1);
    }

    /**
     * Get a day from its number (1 = Monday, 7 = Sunday).
     */
    public static function fromNumber(int $number): self
    {
        return match ($number) {
            1 => self::MONDAY,
            2 => self::TUESDAY,
            3 => self::WEDNESDAY,
            4 => self::THURSDAY,
            5 => self::FRIDAY,
            6 => self::SATURDAY,
            7 => self::SUNDAY,
            default => throw new \InvalidArgumentException("Invalid day number: {$number}"),
        };
    }
}
