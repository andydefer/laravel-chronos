<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\ValueObjects;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SlotVOTest extends TestCase
{
    private DateTimeZuluVO $start;

    private DateTimeZuluVO $end;

    protected function setUp(): void
    {
        parent::setUp();
        $this->start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $this->end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');
    }

    public function test_creates_slot_with_valid_parameters(): void
    {
        $slot = new SlotVO($this->start, $this->end, 30);

        $this->assertSame($this->start, $slot->getStart());
        $this->assertSame($this->end, $slot->getEnd());
        $this->assertSame(30, $slot->getDurationInMinutes());
    }

    public function test_throws_exception_when_start_is_after_end(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot start must be before end.');

        new SlotVO($this->end, $this->start, 30);
    }

    public function test_throws_exception_when_duration_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot duration mismatch. Expected: 60 minutes, Actual: 30 minutes.');

        new SlotVO($this->start, $this->end, 60);
    }

    public function test_creates_slot_from_duration(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $this->assertSame($this->start, $slot->getStart());
        $this->assertSame('2024-01-15T10:30:00Z', $slot->getEnd()->getValue());
        $this->assertSame(30, $slot->getDurationInMinutes());
    }

    public function test_returns_duration_formatted_in_minutes(): void
    {
        $slot = SlotVO::fromDuration($this->start, 45);

        $this->assertSame('45m', $slot->getDurationFormatted());
    }

    public function test_returns_duration_formatted_in_hours(): void
    {
        $slot = SlotVO::fromDuration($this->start, 120);

        $this->assertSame('2h', $slot->getDurationFormatted());
    }

    public function test_returns_duration_formatted_in_hours_and_minutes(): void
    {
        $slot = SlotVO::fromDuration($this->start, 150);

        $this->assertSame('2h 30m', $slot->getDurationFormatted());
    }

    public function test_checks_overlap_with_time_range(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $overlapStart = DateTimeZuluVO::from('2024-01-15T10:15:00Z');
        $overlapEnd = DateTimeZuluVO::from('2024-01-15T10:45:00Z');

        $this->assertTrue($slot->overlapsWith($overlapStart, $overlapEnd));
        $this->assertFalse($slot->overlapsWith($overlapEnd, $overlapEnd->addHours(1)));
    }

    public function test_checks_overlap_with_another_slot(): void
    {
        $slot1 = SlotVO::fromDuration($this->start, 30);
        $slot2 = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T10:15:00Z'),
            30
        );

        $this->assertTrue($slot1->overlapsWithSlot($slot2));
    }

    public function test_checks_if_contained_in_range(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:00:00Z');

        $this->assertTrue($slot->isContainedIn($start, $end));
    }

    public function test_checks_if_not_contained_in_range(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $start = DateTimeZuluVO::from('2024-01-15T10:15:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:45:00Z');

        $this->assertFalse($slot->isContainedIn($start, $end));
    }

    public function test_checks_if_contains_datetime(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $inside = DateTimeZuluVO::from('2024-01-15T10:15:00Z');
        $outside = DateTimeZuluVO::from('2024-01-15T10:45:00Z');

        $this->assertTrue($slot->contains($inside));
        $this->assertFalse($slot->contains($outside));
    }

    public function test_checks_adjacency(): void
    {
        $slot1 = SlotVO::fromDuration($this->start, 30);
        $slot2 = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            30
        );

        $this->assertTrue($slot1->isAdjacentTo($slot2));
    }

    public function test_merges_adjacent_slots(): void
    {
        $slot1 = SlotVO::fromDuration($this->start, 30);
        $slot2 = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            30
        );

        $merged = $slot1->merge($slot2);

        $this->assertNotNull($merged);
        $this->assertSame('2024-01-15T10:00:00Z', $merged->getStart()->getValue());
        $this->assertSame('2024-01-15T11:00:00Z', $merged->getEnd()->getValue());
        $this->assertSame(60, $merged->getDurationInMinutes());
    }

    public function test_returns_null_when_merging_non_adjacent_slots(): void
    {
        $slot1 = SlotVO::fromDuration($this->start, 30);
        $slot2 = SlotVO::fromDuration(
            DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
            30
        );

        $merged = $slot1->merge($slot2);

        $this->assertNull($merged);
    }

    public function test_splits_slot_into_chunks(): void
    {
        $slot = SlotVO::fromDuration($this->start, 60);

        $chunks = $slot->split(30);

        $this->assertCount(2, $chunks);
        $this->assertSame('2024-01-15T10:00:00Z', $chunks[0]->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $chunks[0]->getEnd()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $chunks[1]->getStart()->getValue());
        $this->assertSame('2024-01-15T11:00:00Z', $chunks[1]->getEnd()->getValue());
    }

    public function test_returns_single_slot_when_chunk_duration_larger(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $chunks = $slot->split(60);

        $this->assertCount(1, $chunks);
        $this->assertSame($slot, $chunks[0]);
    }

    public function test_throws_exception_when_chunk_duration_is_zero_or_negative(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk duration must be positive.');

        $slot->split(0);
    }

    public function test_splits_with_remaining_time(): void
    {
        $slot = SlotVO::fromDuration($this->start, 45);

        $chunks = $slot->split(20);

        $this->assertCount(3, $chunks);
        $this->assertSame('2024-01-15T10:00:00Z', $chunks[0]->getStart()->getValue());
        $this->assertSame('2024-01-15T10:20:00Z', $chunks[0]->getEnd()->getValue());
        $this->assertSame('2024-01-15T10:20:00Z', $chunks[1]->getStart()->getValue());
        $this->assertSame('2024-01-15T10:40:00Z', $chunks[1]->getEnd()->getValue());
        $this->assertSame('2024-01-15T10:40:00Z', $chunks[2]->getStart()->getValue());
        $this->assertSame('2024-01-15T10:45:00Z', $chunks[2]->getEnd()->getValue());
        $this->assertSame(5, $chunks[2]->getDurationInMinutes());
    }

    public function test_returns_associative_array(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $array = $slot->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('start', $array);
        $this->assertArrayHasKey('end', $array);
        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('duration_formatted', $array);
        $this->assertSame('2024-01-15T10:00:00Z', $array['start']);
        $this->assertSame('2024-01-15T10:30:00Z', $array['end']);
        $this->assertSame(30, $array['duration']);
        $this->assertSame('30m', $array['duration_formatted']);
    }

    public function test_get_value_returns_associative(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $value = $slot->getValue();

        $this->assertInstanceOf(Associative::class, $value);
        $this->assertSame('2024-01-15T10:00:00Z', $value->get('start'));
        $this->assertSame('2024-01-15T10:30:00Z', $value->get('end'));
        $this->assertSame(30, $value->get('duration'));
    }

    public function test_to_string_returns_human_readable_format(): void
    {
        $slot = SlotVO::fromDuration($this->start, 30);

        $this->assertSame('2024-01-15 10:00:00 - 2024-01-15 10:30:00 (30m)', (string) $slot);
    }
}
