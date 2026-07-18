<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use Illuminate\Support\Collection;

/**
 * Collection of SlotVO objects.
 *
 * @extends AbstractTypedCollection<SlotVO>
 */
final class SlotVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SlotVO::class);
    }

    /**
     * Get all slots that start after a given time.
     */
    public function after(DateTimeZuluVO $time): self
    {
        $filtered = $this->filter(fn (SlotVO $slot) => $slot->getStart()->isAfter($time));

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get all slots that start before a given time.
     */
    public function before(DateTimeZuluVO $time): self
    {
        $filtered = $this->filter(fn (SlotVO $slot) => $slot->getStart()->isBefore($time));

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get all slots that are within a specific date range.
     */
    public function within(DateTimeZuluVO $start, DateTimeZuluVO $end): self
    {
        $filtered = $this->filter(
            fn (SlotVO $slot) => $slot->isContainedIn($start, $end)
        );

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get all slots with a specific duration.
     */
    public function withDuration(int $durationInMinutes): self
    {
        $filtered = $this->filter(
            fn (SlotVO $slot) => $slot->getDurationInMinutes() === $durationInMinutes
        );

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get all slots with duration greater than or equal to a minimum.
     */
    public function withMinDuration(int $minDurationInMinutes): self
    {
        $filtered = $this->filter(
            fn (SlotVO $slot) => $slot->getDurationInMinutes() >= $minDurationInMinutes
        );

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get all slots with duration less than or equal to a maximum.
     */
    public function withMaxDuration(int $maxDurationInMinutes): self
    {
        $filtered = $this->filter(
            fn (SlotVO $slot) => $slot->getDurationInMinutes() <= $maxDurationInMinutes
        );

        $collection = new self;
        foreach ($filtered->items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Get the first slot (earliest start time).
     */
    public function firstSlot(): ?SlotVO
    {
        if ($this->isEmpty()) {
            return null;
        }

        $sorted = $this->sortByStart();

        return $sorted->items[0] ?? null;
    }

    /**
     * Get the last slot (latest start time).
     */
    public function lastSlot(): ?SlotVO
    {
        if ($this->isEmpty()) {
            return null;
        }

        $sorted = $this->sortByStartDesc();

        return $sorted->items[0] ?? null;
    }

    /**
     * Get the earliest start time among all slots.
     */
    public function getEarliestStart(): ?DateTimeZuluVO
    {
        $first = $this->firstSlot();

        return $first?->getStart();
    }

    /**
     * Get the latest end time among all slots.
     */
    public function getLatestEnd(): ?DateTimeZuluVO
    {
        $last = $this->lastSlot();

        return $last?->getEnd();
    }

    /**
     * Sort slots by start time (ascending).
     * Utilise les timestamps pour comparer correctement.
     */
    public function sortByStart(): self
    {
        $items = $this->items;
        usort($items, function (SlotVO $a, SlotVO $b) {
            return $a->getStart()->getCarbon()->timestamp <=> $b->getStart()->getCarbon()->timestamp;
        });

        $collection = new self;
        foreach ($items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Sort slots by start time (descending).
     * Utilise les timestamps pour comparer correctement.
     */
    public function sortByStartDesc(): self
    {
        $items = $this->items;
        usort($items, function (SlotVO $a, SlotVO $b) {
            return $b->getStart()->getCarbon()->timestamp <=> $a->getStart()->getCarbon()->timestamp;
        });

        $collection = new self;
        foreach ($items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Sort slots by duration (ascending).
     */
    public function sortByDuration(): self
    {
        $items = $this->items;
        usort($items, fn (SlotVO $a, SlotVO $b) => $a->getDurationInMinutes() <=> $b->getDurationInMinutes());

        $collection = new self;
        foreach ($items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Sort slots by duration (descending).
     */
    public function sortByDurationDesc(): self
    {
        $items = $this->items;
        usort($items, fn (SlotVO $a, SlotVO $b) => $b->getDurationInMinutes() <=> $a->getDurationInMinutes());

        $collection = new self;
        foreach ($items as $slot) {
            $collection->add($slot);
        }

        return $collection;
    }

    /**
     * Convert to a Laravel Collection.
     *
     * @return Collection<int, SlotVO>
     */
    public function toBaseCollection(): Collection
    {
        return new Collection($this->items);
    }

    /**
     * Check if any slot overlaps with a given time range.
     */
    public function hasOverlapWith(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        foreach ($this->items as $slot) {
            if ($slot->overlapsWith($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any slot overlaps with another slot.
     */
    public function hasOverlapWithSlot(SlotVO $slot): bool
    {
        foreach ($this->items as $item) {
            if ($item->overlapsWithSlot($slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the total available time in minutes across all slots.
     */
    public function getTotalAvailableMinutes(): int
    {
        $total = 0;
        foreach ($this->items as $slot) {
            $total += $slot->getDurationInMinutes();
        }

        return $total;
    }

    /**
     * Get the total available time formatted as a human-readable string.
     */
    public function getTotalAvailableFormatted(): string
    {
        $totalMinutes = $this->getTotalAvailableMinutes();
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Count the number of slots.
     */
    public function countSlots(): int
    {
        return $this->count();
    }
}
