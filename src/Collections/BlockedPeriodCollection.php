<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelChronos\ValueObjects\BlockedPeriodVO;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Collection of BlockedPeriodVO objects.
 *
 * Provides specialized methods for working with blocked periods,
 * including filtering, sorting, and analysis.
 *
 * @extends TypedCollection<BlockedPeriodVO>
 *
 * @example
 * $collection = new BlockedPeriodCollection();
 * $collection->add(new BlockedPeriodVO(...));
 *
 * $schedules = $collection->filterByType('schedule');
 * $totalBlocked = $collection->getTotalDuration();
 */
final class BlockedPeriodCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(BlockedPeriodVO::class);
    }

    /**
     * Creates a collection from an array of blocked period data.
     *
     * @param  array<array{start: string, end: string, type: string, id: int}>  $periods
     */
    public static function fromArray(array $periods): self
    {
        $collection = new self;

        foreach ($periods as $period) {
            $collection->add(BlockedPeriodVO::from($period));
        }

        return $collection;
    }

    /**
     * Filters periods by type.
     *
     * @param  string  $type  The type to filter by ('schedule' or 'impediment')
     * @return self A new collection containing only periods of the specified type
     */
    public function filterByType(string $type): self
    {
        $collection = new self;

        foreach ($this->items as $period) {
            if ($period->getType() === $type) {
                $collection->add($period);
            }
        }

        return $collection;
    }

    /**
     * Gets all schedule periods.
     *
     * @return self Collection of schedule blocked periods
     */
    public function getSchedules(): self
    {
        return $this->filterByType('schedule');
    }

    /**
     * Gets all impediment periods.
     *
     * @return self Collection of impediment blocked periods
     */
    public function getImpediments(): self
    {
        return $this->filterByType('impediment');
    }

    /**
     * Filters periods that overlap with a given time range.
     *
     * @param  DateTimeZuluVO  $start  The start of the range
     * @param  DateTimeZuluVO  $end  The end of the range
     * @return self Collection of overlapping periods
     */
    public function filterOverlapping(DateTimeZuluVO $start, DateTimeZuluVO $end): self
    {
        $collection = new self;

        foreach ($this->items as $period) {
            if ($period->overlapsWith($start, $end)) {
                $collection->add($period);
            }
        }

        return $collection;
    }

    /**
     * Filters periods that contain a given datetime.
     *
     * @param  DateTimeZuluVO  $datetime  The datetime to check
     * @return self Collection of periods containing the datetime
     */
    public function filterContaining(DateTimeZuluVO $datetime): self
    {
        $collection = new self;

        foreach ($this->items as $period) {
            if ($period->contains($datetime)) {
                $collection->add($period);
            }
        }

        return $collection;
    }

    /**
     * Sorts periods by start time (ascending).
     *
     * @return self A new sorted collection
     */
    public function sortByStart(): self
    {
        $items = $this->items;
        usort($items, function (BlockedPeriodVO $a, BlockedPeriodVO $b): int {
            return $a->getStart()->diffInSeconds($b->getStart()) <=> 0;
        });

        $collection = new self;
        foreach ($items as $period) {
            $collection->add($period);
        }

        return $collection;
    }

    /**
     * Sorts periods by duration (ascending).
     *
     * @return self A new sorted collection
     */
    public function sortByDuration(): self
    {
        $items = $this->items;
        usort($items, function (BlockedPeriodVO $a, BlockedPeriodVO $b): int {
            return $a->getDurationInMinutes() <=> $b->getDurationInMinutes();
        });

        $collection = new self;
        foreach ($items as $period) {
            $collection->add($period);
        }

        return $collection;
    }

    /**
     * Gets the total duration of all periods in minutes.
     *
     * @return int Total duration in minutes
     */
    public function getTotalDuration(): int
    {
        $total = 0;

        foreach ($this->items as $period) {
            $total += $period->getDurationInMinutes();
        }

        return $total;
    }

    /**
     * Gets the total duration of schedule periods in minutes.
     *
     * @return int Total schedule duration in minutes
     */
    public function getTotalScheduleDuration(): int
    {
        return $this->getSchedules()->getTotalDuration();
    }

    /**
     * Gets the total duration of impediment periods in minutes.
     *
     * @return int Total impediment duration in minutes
     */
    public function getTotalImpedimentDuration(): int
    {
        return $this->getImpediments()->getTotalDuration();
    }

    /**
     * Gets the longest period.
     *
     * @return BlockedPeriodVO|null The longest period or null if collection is empty
     */
    public function getLongest(): ?BlockedPeriodVO
    {
        if ($this->isEmpty()) {
            return null;
        }

        $longest = $this->first();

        foreach ($this->items as $period) {
            if ($period->getDurationInMinutes() > $longest->getDurationInMinutes()) {
                $longest = $period;
            }
        }

        return $longest;
    }

    /**
     * Gets the shortest period.
     *
     * @return BlockedPeriodVO|null The shortest period or null if collection is empty
     */
    public function getShortest(): ?BlockedPeriodVO
    {
        if ($this->isEmpty()) {
            return null;
        }

        $shortest = $this->first();

        foreach ($this->items as $period) {
            if ($period->getDurationInMinutes() < $shortest->getDurationInMinutes()) {
                $shortest = $period;
            }
        }

        return $shortest;
    }
}
