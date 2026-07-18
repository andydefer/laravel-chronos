<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

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
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;

final class SlotServiceTest extends IntegrationTestCase
{
    private SlotServiceInterface $slotService;

    private AvailabilityServiceInterface $availabilityService;

    private ScheduleServiceInterface $scheduleService;

    private ImpedimentServiceInterface $impedimentService;

    protected function setUp(): void
    {
        parent::setUp();

        ChronosMutationContext::withAllowed(function () {
            TestCar::create([
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
        $this->createAvailability();

        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $slot = $this->slotService->findNextSlot(
            TestCar::class,
            1,
            $after,
            30
        );

        $this->assertNotNull($slot);
        $this->assertSame('2024-01-15T09:00:00Z', $slot->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slot->getEnd()->getValue());
    }

    public function test_find_next_slot_returns_null_when_no_availability(): void
    {
        $after = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $slot = $this->slotService->findNextSlot(
            TestCar::class,
            99999,
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
            TestCar::class,
            1,
            $start,
            $end,
            30
        );

        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_returns_slots_for_specific_day(): void
    {
        $this->createAvailability();

        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $slots = $this->slotService->findSlotsForDay(
            TestCar::class,
            1,
            $date,
            30
        );

        $this->assertCount(4, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_excludes_blocked_slots(): void
    {
        $availability = $this->createAvailability();

        // Créer un schedule qui bloque un slot
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Blocked Slot',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $slots = $this->slotService->findSlotsForDay(
            TestCar::class,
            1,
            $date,
            30
        );

        // Après blocage de 09:30-10:00, les slots disponibles sont:
        // 09:00-09:30, 10:00-10:30, 10:30-11:00 = 3 slots
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
            TestCar::class,
            1,
            $start,
            $end
        );

        $this->assertTrue($isAvailable);
    }

    public function test_is_slot_available_returns_false_for_unavailable_slot(): void
    {
        $this->createAvailability();

        $start = DateTimeZuluVO::from('2024-01-15T08:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T08:30:00Z');

        $isAvailable = $this->slotService->isSlotAvailable(
            TestCar::class,
            1,
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
            TestCar::class,
            1,
            $after,
            30
        );

        $this->assertNotNull($start);
        $this->assertSame('2024-01-15T09:00:00Z', $start->getValue());
    }

    public function test_has_availability_on_date_returns_true_when_available(): void
    {
        $this->createAvailability();

        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            TestCar::class,
            1,
            $date
        );

        $this->assertTrue($hasAvailability);
    }

    public function test_has_availability_on_date_returns_false_when_not_available(): void
    {
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        $hasAvailability = $this->slotService->hasAvailabilityOnDate(
            TestCar::class,
            99999,
            $date
        );

        $this->assertFalse($hasAvailability);
    }

    public function test_get_blocked_periods_returns_blocked_periods(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Blocked Period',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:00:00Z');

        $blocked = $this->slotService->getBlockedPeriods(
            TestCar::class,
            1,
            $start,
            $end
        );

        $this->assertCount(1, $blocked);
        $this->assertEquals('schedule', $blocked[0]['type']);
    }

    public function test_generate_slots_from_slot_splits_correctly(): void
    {
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $slot = SlotVO::fromDuration($start, 60);

        $slots = $this->slotService->generateSlotsFromSlot($slot, 30);

        $this->assertCount(2, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->first()->getEnd()->getValue());
        $this->assertSame('2024-01-15T09:30:00Z', $slots->last()->getStart()->getValue());
        $this->assertSame('2024-01-15T10:00:00Z', $slots->last()->getEnd()->getValue());
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
            'schedulable_id' => 1,
        ]);

        return $this->availabilityService->create($record);
    }
}
