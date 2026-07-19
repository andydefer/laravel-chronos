<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

use AndyDefer\LaravelChronos\Collections\BlockedPeriodCollection;
use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\BlockedPeriodVO;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use InvalidArgumentException;

final class SlotServiceTest extends IntegrationTestCase
{
    private SlotServiceInterface $slotService;

    private AvailabilityServiceInterface $availabilityService;

    private ScheduleServiceInterface $scheduleService;

    private ImpedimentServiceInterface $impedimentService;

    private TestCar $testCar;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chronos.min_durations.slot_search', 5);

        $this->testCar = ChronosMutationContext::withAllowed(function () {
            return TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST123',
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });

        $this->slotService = $this->app->make(SlotServiceInterface::class);
        $this->availabilityService = $this->app->make(AvailabilityServiceInterface::class);
        $this->scheduleService = $this->app->make(ScheduleServiceInterface::class);
        $this->impedimentService = $this->app->make(ImpedimentServiceInterface::class);
    }

    // ============================================================
    // TESTS ORIGINAUX
    // ============================================================

    public function test_find_next_slot_returns_available_slot(): void
    {
        $this->createAvailability();
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        $slot = $this->slotService->findNextSlot(
            $this->testCar,
            $after,
            30
        );

        $this->assertNotNull($slot);
        $this->assertSame('2024-01-15T09:00:00Z', $slot->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slot->getEnd()->getValue());
    }

    public function test_find_next_slot_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        $this->slotService->findNextSlot(
            $this->testCar,
            $after,
            1
        );
    }

    public function test_find_next_slot_returns_null_when_no_availability(): void
    {
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        $slot = $this->slotService->findNextSlot(
            $unknownCar,
            $after,
            30
        );

        $this->assertNull($slot);
    }

    public function test_find_slots_in_range_returns_all_available_slots(): void
    {
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            30
        );

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_in_range_with_limit(): void
    {
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            30,
            null,
            2
        );

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_in_range_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            1
        );
    }

    public function test_find_slots_for_day_returns_slots_for_specific_day(): void
    {
        $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            30
        );

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_with_limit(): void
    {
        $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            30,
            null,
            2
        );

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            1
        );
    }

    public function test_find_slots_for_day_excludes_blocked_slots(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Slot',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            30
        );

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(3, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_is_slot_available_returns_true_for_available_slot(): void
    {
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T09:30:00Z');

        $isAvailable = $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );

        $this->assertTrue($isAvailable);
    }

    public function test_is_slot_available_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T09:01:00Z');

        $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );
    }

    public function test_is_slot_available_returns_false_for_unavailable_slot(): void
    {
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T08:30:00Z');

        $isAvailable = $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );

        $this->assertFalse($isAvailable);
    }

    public function test_get_next_available_start_returns_correct_time(): void
    {
        $this->createAvailability();
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        $start = $this->slotService->getNextAvailableStart(
            $this->testCar,
            $after,
            30
        );

        $this->assertNotNull($start);
        $this->assertSame('2024-01-15T09:00:00Z', $start->getValue());
    }

    public function test_get_next_available_start_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        $this->slotService->getNextAvailableStart(
            $this->testCar,
            $after,
            1
        );
    }

    public function test_has_availability_on_date_returns_true_when_available(): void
    {
        $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            $this->testCar,
            $date
        );

        $this->assertTrue($hasAvailability);
    }

    public function test_has_availability_on_date_returns_false_when_not_available(): void
    {
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            $unknownCar,
            $date
        );

        $this->assertFalse($hasAvailability);
    }

    public function test_get_blocked_periods_returns_blocked_period_collection(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Period',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:00:00Z');

        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(1, $blocked);

        $first = $blocked->first();
        $this->assertInstanceOf(BlockedPeriodVO::class, $first);
        $this->assertSame('schedule', $first->getType());
        $this->assertSame(30, $first->getDurationInMinutes());
    }

    public function test_get_blocked_periods_with_limit(): void
    {
        $availability = $this->createAvailability();

        for ($i = 1; $i <= 5; $i++) {
            $hour = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            ChronosMutationContext::withAllowed(function () use ($availability, $i, $hour) {
                Schedule::create([
                    'availability_id' => $availability->id,
                    'schedulable_type' => TestCar::class,
                    'schedulable_id' => $this->testCar->id,
                    'title' => "Blocked Period $i",
                    'start_datetime' => "2024-01-15 $hour:30:00",
                    'end_datetime' => '2024-01-15 '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).':00:00',
                ]);
            });
        }

        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end,
            null,
            3
        );

        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(3, $blocked);

        // Vérifie que les 3 premiers sont retournés (par start time croissant)
        $titles = $blocked->map(fn ($p) => $p->getId())->toArray();
        $this->assertCount(3, $titles);
    }

    public function test_generate_slots_from_slot_splits_correctly(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 60);

        $slots = $this->slotService->generateSlotsFromSlot($slot, 30);

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->first()->getEnd()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:00:00Z', $slots->last()->getEnd()->getValue());
    }

    public function test_generate_slots_from_slot_with_limit(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 120);

        $slots = $this->slotService->generateSlotsFromSlot($slot, 30, 2);

        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->first()->getEnd()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:00:00Z', $slots->last()->getEnd()->getValue());
    }

    public function test_generate_slots_from_slot_throws_exception_when_duration_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 60);

        $this->slotService->generateSlotsFromSlot($slot, 1);
    }

    public function test_get_blocked_periods_returns_schedule_and_impediment_blockers(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Schedule',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Schedule 2',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(2, $blocked);
        $this->assertEquals('schedule', $blocked->first()->getType());
        $this->assertEquals(30, $blocked->first()->getDurationInMinutes());
        $this->assertEquals(60, $blocked->getTotalDuration());
    }

    public function test_blocked_period_collection_helpers(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Schedule',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Schedule 2',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(2, $blocked);

        $first = $blocked->first();
        $this->assertInstanceOf(BlockedPeriodVO::class, $first);
        $this->assertEquals('schedule', $first->getType());
        $this->assertEquals(30, $first->getDurationInMinutes());
        $this->assertEquals(60, $blocked->getTotalDuration());
        $this->assertEquals(60, $blocked->getTotalScheduleDuration());
        $this->assertEquals(0, $blocked->getTotalImpedimentDuration());
    }

    private function createAvailability(): Availability
    {
        $record = AvailabilityRecord::from([
            'name' => 'Test Availability',
            'type' => 'test',
            'days' => ['monday', 'wednesday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '11:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        return $this->availabilityService->create($record);
    }
}
