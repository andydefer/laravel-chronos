<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelChronos\Enums\WeekDay;

/**
 * Collection of WeekDay enums.
 *
 * @extends TypedCollection<WeekDay>
 */
final class WeekDayCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(WeekDay::class);
    }

    /**
     * Create a collection from an array of strings.
     *
     * @param  array<string>  $days
     */
    public static function fromStrings(array $days): self
    {
        $collection = new self;

        foreach ($days as $day) {
            $enum = WeekDay::fromString($day);
            if ($enum !== null) {
                $collection->add($enum);
            }
        }

        return $collection;
    }

    /**
     * Create a collection from an array of integers (1 = Monday, 7 = Sunday).
     *
     * @param  array<int>  $numbers
     */
    public static function fromNumbers(array $numbers): self
    {
        $collection = new self;

        foreach ($numbers as $number) {
            try {
                $collection->add(WeekDay::fromNumber($number));
            } catch (\InvalidArgumentException) {
                // Skip invalid numbers
            }
        }

        return $collection;
    }

    /**
     * Get all weekdays (Monday to Friday).
     */
    public static function weekdays(): self
    {
        $collection = new self;
        $collection->add(...WeekDay::weekdays());

        return $collection;
    }

    /**
     * Get all weekend days (Saturday and Sunday).
     */
    public static function weekends(): self
    {
        $collection = new self;
        $collection->add(...WeekDay::weekends());

        return $collection;
    }

    /**
     * Check if a specific day is in the collection.
     */
    public function containsDay(WeekDay $day): bool
    {
        return $this->contains($day);
    }

    /**
     * Check if any weekend day is in the collection.
     */
    public function hasWeekend(): bool
    {
        foreach ($this->items as $day) {
            if ($day->isWeekend()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all days that are weekdays from the collection.
     */
    public function getWeekdays(): self
    {
        $filtered = $this->filter(fn (WeekDay $day): bool => $day->isWeekday());

        $collection = new self;
        foreach ($filtered->items as $day) {
            $collection->add($day);
        }

        return $collection;
    }

    /**
     * Get all days that are weekends from the collection.
     */
    public function getWeekends(): self
    {
        $filtered = $this->filter(fn (WeekDay $day): bool => $day->isWeekend());

        $collection = new self;
        foreach ($filtered->items as $day) {
            $collection->add($day);
        }

        return $collection;
    }

    /**
     * Convert the collection to an array of strings.
     *
     * @return array<string>
     */
    public function toStrings(): array
    {
        return array_map(fn (WeekDay $day): string => $day->value, $this->items);
    }

    /**
     * Convert the collection to an array of integers (1 = Monday, 7 = Sunday).
     *
     * @return array<int>
     */
    public function toNumbers(): array
    {
        return array_map(fn (WeekDay $day): int => $day->getNumber(), $this->items);
    }

    /**
     * Convert the collection to an array of labels.
     *
     * @return array<string>
     */
    public function toLabels(): array
    {
        return array_map(fn (WeekDay $day): string => $day->getLabel(), $this->items);
    }

    /**
     * Convert the collection to a StringTypedCollection of labels.
     */
    public function toLabelCollection(): StringTypedCollection
    {
        $labels = new StringTypedCollection;

        foreach ($this->items as $day) {
            $labels->add($day->getLabel());
        }

        return $labels;
    }

    /**
     * Check if the collection contains all days of the week.
     */
    public function isFullWeek(): bool
    {
        return $this->count() === 7;
    }

    /**
     * Check if the collection contains all weekdays (Monday to Friday).
     */
    public function isFullWeekdays(): bool
    {
        $weekdays = WeekDay::weekdays();
        $count = 0;

        foreach ($weekdays as $day) {
            if ($this->containsDay($day)) {
                $count++;
            }
        }

        return $count === 5;
    }

    /**
     * Get the count of days in the collection.
     */
    public function countDays(): int
    {
        return $this->count();
    }

    /**
     * Sort the collection in natural order (Monday to Sunday).
     */
    public function sortNatural(): self
    {
        $items = $this->items;
        usort($items, fn (WeekDay $a, WeekDay $b): int => $a->getNumber() <=> $b->getNumber());

        $collection = new self;
        foreach ($items as $day) {
            $collection->add($day);
        }

        return $collection;
    }

    /**
     * Check if a day is in the collection by string.
     */
    public function containsString(string $day): bool
    {
        $enum = WeekDay::fromString($day);

        if ($enum === null) {
            return false;
        }

        return $this->containsDay($enum);
    }

    /**
     * Check if a day is in the collection by number.
     */
    public function containsNumber(int $number): bool
    {
        try {
            $enum = WeekDay::fromNumber($number);

            return $this->containsDay($enum);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
