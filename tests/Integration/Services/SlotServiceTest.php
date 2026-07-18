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

        // Configuration for testing
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

    public function test_find_next_slot_returns_available_slot(): void
    {
        // Arrange
        $this->createAvailability();
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        // Act
        $slot = $this->slotService->findNextSlot(
            $this->testCar,
            $after,
            30
        );

        // Assert
        $this->assertNotNull($slot);
        $this->assertSame('2024-01-15T09:00:00Z', $slot->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slot->getEnd()->getValue());
    }

    public function test_find_next_slot_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        // Act
        $this->slotService->findNextSlot(
            $this->testCar,
            $after,
            1 // Duration too short
        );
    }

    public function test_find_next_slot_returns_null_when_no_availability(): void
    {
        // Arrange
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        // Act
        $slot = $this->slotService->findNextSlot(
            $unknownCar,
            $after,
            30
        );

        // Assert
        $this->assertNull($slot);
    }

    public function test_find_slots_in_range_returns_all_available_slots(): void
    {
        // Arrange
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        // Act
        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            30
        );

        // Assert
        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_in_range_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        // Act
        $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            1 // Duration too short
        );
    }

    public function test_find_slots_for_day_returns_slots_for_specific_day(): void
    {
        // Arrange
        $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            30
        );

        // Assert
        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            1 // Duration too short
        );
    }

    public function test_find_slots_for_day_excludes_blocked_slots(): void
    {
        // Arrange
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

        // Act
        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            $date,
            30
        );

        // Assert
        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(3, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_is_slot_available_returns_true_for_available_slot(): void
    {
        // Arrange
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T09:30:00Z');

        // Act
        $isAvailable = $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );

        // Assert
        $this->assertTrue($isAvailable);
    }

    public function test_is_slot_available_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T09:01:00Z');

        // Act
        $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );
    }

    public function test_is_slot_available_returns_false_for_unavailable_slot(): void
    {
        // Arrange
        $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T08:30:00Z');

        // Act
        $isAvailable = $this->slotService->isSlotAvailable(
            $this->testCar,
            $start,
            $end
        );

        // Assert
        $this->assertFalse($isAvailable);
    }

    public function test_get_next_available_start_returns_correct_time(): void
    {
        // Arrange
        $this->createAvailability();
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        // Act
        $start = $this->slotService->getNextAvailableStart(
            $this->testCar,
            $after,
            30
        );

        // Assert
        $this->assertNotNull($start);
        $this->assertSame('2024-01-15T09:00:00Z', $start->getValue());
    }

    public function test_get_next_available_start_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');

        // Act
        $this->slotService->getNextAvailableStart(
            $this->testCar,
            $after,
            1 // Duration too short
        );
    }

    public function test_has_availability_on_date_returns_true_when_available(): void
    {
        // Arrange
        $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            $this->testCar,
            $date
        );

        // Assert
        $this->assertTrue($hasAvailability);
    }

    public function test_has_availability_on_date_returns_false_when_not_available(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        // Act
        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            $unknownCar,
            $date
        );

        // Assert
        $this->assertFalse($hasAvailability);
    }

    public function test_get_blocked_periods_returns_blocked_period_collection(): void
    {
        // Arrange
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

        // Act
        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        // Assert
        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(1, $blocked);

        $first = $blocked->first();
        $this->assertInstanceOf(BlockedPeriodVO::class, $first);
        $this->assertSame('schedule', $first->getType());
        $this->assertSame(30, $first->getDurationInMinutes());
    }

    public function test_generate_slots_from_slot_splits_correctly(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 60);

        // Act
        $slots = $this->slotService->generateSlotsFromSlot($slot, 30);

        // Assert
        $this->assertInstanceOf(SlotVOCollection::class, $slots);
        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->first()->getEnd()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:00:00Z', $slots->last()->getEnd()->getValue());
    }

    public function test_generate_slots_from_slot_throws_exception_when_duration_too_short(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.');
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 60);

        // Act
        $this->slotService->generateSlotsFromSlot($slot, 1);
    }

    public function test_get_blocked_periods_returns_schedule_and_impediment_blockers(): void
    {
        // Arrange
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

        // Act
        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        // Assert
        $this->assertInstanceOf(BlockedPeriodCollection::class, $blocked);
        $this->assertCount(2, $blocked);
        $this->assertEquals('schedule', $blocked->first()->getType());
        $this->assertEquals(30, $blocked->first()->getDurationInMinutes());
        $this->assertEquals(60, $blocked->getTotalDuration());
    }

    public function test_blocked_period_collection_helpers(): void
    {
        // Arrange
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

        // Act
        $blocked = $this->slotService->getBlockedPeriods(
            $this->testCar,
            $start,
            $end
        );

        // Assert
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
