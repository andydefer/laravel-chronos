<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Collections;

use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class SlotVOCollectionTest extends TestCase
{
    private SlotVOCollection $collection;

    private DateTimeZuluVO $baseStart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseStart = DateTimeZuluVO::from('2024-01-15T09:00:00Z');

        // Créer des slots triés par ordre chronologique
        $this->collection = new SlotVOCollection;
        $this->collection->add(
            SlotVO::fromDuration($this->baseStart, 30), // 09:00 - 09:30
            SlotVO::fromDuration($this->baseStart->addMinutes(60), 30), // 10:00 - 10:30
            SlotVO::fromDuration($this->baseStart->addMinutes(120), 30), // 11:00 - 11:30
            SlotVO::fromDuration($this->baseStart->addMinutes(180), 30), // 12:00 - 12:30
        );

        // DEBUG: Afficher le contenu de la collection
        foreach ($this->collection as $slot) {
        }
    }

    public function test_creates_empty_collection(): void
    {
        $collection = new SlotVOCollection;

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function test_adds_slots_to_collection(): void
    {
        $collection = new SlotVOCollection;
        $slot = SlotVO::fromDuration($this->baseStart, 30);

        $collection->add($slot);

        $this->assertFalse($collection->isEmpty());
        $this->assertSame(1, $collection->count());
        $this->assertSame($slot, $collection->first());
    }

    public function test_filters_slots_after_given_time(): void
    {
        $time = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $filtered = $this->collection->after($time);

        $this->assertSame(3, $filtered->count());
        $this->assertSame('2024-01-15T10:00:00Z', $filtered->first()->getStart()->getValue());
    }

    public function test_returns_empty_when_no_slots_after_time(): void
    {
        $time = DateTimeZuluVO::from('2024-01-15T13:00:00Z');
        $filtered = $this->collection->after($time);

        $this->assertTrue($filtered->isEmpty());
        $this->assertSame(0, $filtered->count());
    }

    public function test_filters_slots_before_given_time(): void
    {
        $time = DateTimeZuluVO::from('2024-01-15T11:30:00Z');
        $filtered = $this->collection->before($time);

        $this->assertSame(3, $filtered->count());
        $this->assertSame('2024-01-15T09:00:00Z', $filtered->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T11:00:00Z', $filtered->last()->getStart()->getValue());
    }

    public function test_filters_slots_within_date_range(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');
        $filtered = $this->collection->within($start, $end);

        $this->assertSame(2, $filtered->count());
        $this->assertSame('2024-01-15T10:00:00Z', $filtered->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T11:00:00Z', $filtered->last()->getStart()->getValue());
    }

    public function test_filters_slots_by_duration(): void
    {
        $filtered = $this->collection->withDuration(30);

        $this->assertSame(4, $filtered->count());
    }

    public function test_returns_empty_when_no_slots_with_duration(): void
    {
        $filtered = $this->collection->withDuration(60);

        $this->assertTrue($filtered->isEmpty());
    }

    public function test_filters_slots_with_minimum_duration(): void
    {
        $collection = new SlotVOCollection;
        $collection->add(
            SlotVO::fromDuration($this->baseStart, 15),
            SlotVO::fromDuration($this->baseStart->addMinutes(30), 30),
            SlotVO::fromDuration($this->baseStart->addMinutes(90), 60),
        );

        $filtered = $collection->withMinDuration(30);

        $this->assertSame(2, $filtered->count());
        $this->assertSame(30, $filtered->first()->getDurationInMinutes());
        $this->assertSame(60, $filtered->last()->getDurationInMinutes());
    }

    public function test_filters_slots_with_maximum_duration(): void
    {
        $collection = new SlotVOCollection;
        $collection->add(
            SlotVO::fromDuration($this->baseStart, 15),
            SlotVO::fromDuration($this->baseStart->addMinutes(30), 30),
            SlotVO::fromDuration($this->baseStart->addMinutes(90), 60),
        );

        $filtered = $collection->withMaxDuration(30);

        $this->assertSame(2, $filtered->count());
        $this->assertSame(15, $filtered->first()->getDurationInMinutes());
        $this->assertSame(30, $filtered->last()->getDurationInMinutes());
    }

    public function test_returns_first_slot(): void
    {
        foreach ($this->collection as $slot) {
        }

        $sorted = $this->collection->sortByStart();
        foreach ($sorted as $slot) {
        }

        $first = $sorted->firstSlot();

        $this->assertNotNull($first);
        $this->assertSame('2024-01-15T09:00:00Z', $first->getStart()->getValue());
    }

    public function test_returns_null_when_first_slot_on_empty_collection(): void
    {
        $collection = new SlotVOCollection;
        $first = $collection->firstSlot();

        $this->assertNull($first);
    }

    public function test_returns_last_slot(): void
    {
        $last = $this->collection->lastSlot();

        $this->assertNotNull($last);
        $this->assertSame('2024-01-15T12:00:00Z', $last->getStart()->getValue());
    }

    public function test_returns_null_when_last_slot_on_empty_collection(): void
    {
        $collection = new SlotVOCollection;
        $last = $collection->lastSlot();

        $this->assertNull($last);
    }

    public function test_returns_earliest_start(): void
    {
        $earliest = $this->collection->getEarliestStart();

        $this->assertNotNull($earliest);
        $this->assertSame('2024-01-15T09:00:00Z', $earliest->getValue());
    }

    public function test_returns_latest_end(): void
    {
        $latest = $this->collection->getLatestEnd();

        $this->assertNotNull($latest);
        $this->assertSame('2024-01-15T12:30:00Z', $latest->getValue());
    }

    public function test_sorts_by_start_ascending(): void
    {
        foreach ($this->collection as $slot) {
        }

        $sorted = $this->collection->sortByStart();

        foreach ($sorted as $slot) {
        }

        $this->assertSame(4, $sorted->count());
        $this->assertSame('2024-01-15T09:00:00Z', $sorted->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T12:00:00Z', $sorted->last()->getStart()->getValue());
    }

    public function test_sorts_by_start_descending(): void
    {
        $sorted = $this->collection->sortByStartDesc();

        $this->assertSame(4, $sorted->count());
        $this->assertSame('2024-01-15T12:00:00Z', $sorted->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:00:00Z', $sorted->last()->getStart()->getValue());
    }

    public function test_sorts_by_duration_ascending(): void
    {
        $collection = new SlotVOCollection;
        $collection->add(
            SlotVO::fromDuration($this->baseStart, 60),
            SlotVO::fromDuration($this->baseStart->addMinutes(90), 30),
            SlotVO::fromDuration($this->baseStart->addMinutes(150), 15),
        );

        $sorted = $collection->sortByDuration();

        $this->assertSame(15, $sorted->first()->getDurationInMinutes());
        $this->assertSame(60, $sorted->last()->getDurationInMinutes());
    }

    public function test_converts_to_base_collection(): void
    {
        $base = $this->collection->toBaseCollection();

        $this->assertInstanceOf(Collection::class, $base);
        $this->assertSame(4, $base->count());
    }

    public function test_checks_overlap_with_time_range(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:15:00Z');

        $this->assertTrue($this->collection->hasOverlapWith($start, $end));
    }

    public function test_returns_false_when_no_overlap_with_time_range(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T13:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        $this->assertFalse($this->collection->hasOverlapWith($start, $end));
    }

    public function test_checks_overlap_with_another_slot(): void
    {
        $slot = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T09:15:00Z'),
            30
        );

        $this->assertTrue($this->collection->hasOverlapWithSlot($slot));
    }

    public function test_returns_false_when_no_overlap_with_slot(): void
    {
        $slot = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T08:00:00Z'),
            30
        );

        $this->assertFalse($this->collection->hasOverlapWithSlot($slot));
    }

    public function test_calculates_total_available_minutes(): void
    {
        $total = $this->collection->getTotalAvailableMinutes();

        $this->assertSame(120, $total);
    }

    public function test_formats_total_available_time(): void
    {
        $total = $this->collection->getTotalAvailableFormatted();

        $this->assertSame('2h', $total);
    }

    public function test_formats_total_available_time_with_minutes(): void
    {
        $collection = new SlotVOCollection;
        $collection->add(
            SlotVO::fromDuration($this->baseStart, 30),
            SlotVO::fromDuration($this->baseStart->addMinutes(60), 45),
        );

        $total = $collection->getTotalAvailableFormatted();

        $this->assertSame('1h 15m', $total);
    }

    public function test_counts_slots(): void
    {
        $this->assertSame(4, $this->collection->countSlots());
    }

    public function test_empty_collection_total_available_minutes(): void
    {
        $collection = new SlotVOCollection;

        $this->assertSame(0, $collection->getTotalAvailableMinutes());
        $this->assertSame('0m', $collection->getTotalAvailableFormatted());
    }

    public function test_chained_filtering(): void
    {
        $time = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        $filtered = $this->collection->after($time);

        $withDuration = $filtered->withDuration(30);

        $result = $withDuration->sortByStart();

        $this->assertSame(2, $result->count());
        $this->assertSame('2024-01-15T11:00:00Z', $result->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T12:00:00Z', $result->last()->getStart()->getValue());
    }
}
