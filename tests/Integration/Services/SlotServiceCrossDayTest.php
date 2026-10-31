<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

final class SlotServiceCrossDayTest extends IntegrationTestCase
{
    private SlotServiceInterface $slotService;

    private AvailabilityServiceInterface $availabilityService;

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
    }

    // ============================================================
    // CROSS-DAY SLOT AVAILABILITY
    // ============================================================

    public function test_is_slot_available_returns_true_for_24h_slot_on_full_day_availability(): void
    {
        $this->createFullDayAvailability();

        $start = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T14:00:00Z');

        $this->assertTrue(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_true_for_multi_day_slot(): void
    {
        $this->createFullDayAvailability();

        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-18T10:00:00Z');

        $this->assertTrue(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_true_for_7_day_slot(): void
    {
        $this->createFullDayAvailability();

        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-22T10:00:00Z');

        $this->assertTrue(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_false_when_24h_slot_overlaps_schedule(): void
    {
        $availability = $this->createFullDayAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocker',
                'start_datetime' => '2024-01-15 20:00:00',
                'end_datetime' => '2024-01-15 22:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T14:00:00Z');

        $this->assertFalse(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_false_when_start_is_on_restricted_day(): void
    {
        $this->createFullDayAvailability(['monday']);

        // 2024-01-16 is Tuesday.
        $start = DateTimeZuluVO::from('2024-01-16T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T12:00:00Z');

        $this->assertFalse(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_false_when_end_is_on_restricted_day(): void
    {
        $this->createFullDayAvailability(['monday', 'tuesday']);

        // 2024-01-15 (Monday) 10:00 → 2024-01-17 (Wednesday) 10:00.
        // Wednesday is not in `days`, so the window breaks at Tuesday 23:59:59.
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-17T10:00:00Z');

        $this->assertFalse(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_is_slot_available_returns_true_when_window_is_bounded_by_allowed_days(): void
    {
        $this->createFullDayAvailability(['monday', 'tuesday']);

        // 2024-01-15 (Monday) 10:00 → 2024-01-16 (Tuesday) 20:00.
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T20:00:00Z');

        $this->assertTrue(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    // ============================================================
    // CROSS-DAY SLOT GENERATION
    // ============================================================

    public function test_find_slots_in_range_includes_single_24h_slot(): void
    {
        $this->createFullDayAvailability();

        $start = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T14:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            24 * 60
        );

        $this->assertCount(1, $slots);
        $this->assertSame('2024-01-15T14:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-16T14:00:00Z', $slots->first()->getEnd()->getValue());
    }

    public function test_find_slots_in_range_does_not_cross_restricted_days(): void
    {
        $this->createFullDayAvailability(['monday', 'tuesday', 'wednesday']);

        // Wednesday excluded.
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-18T10:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            72 * 60
        );

        $this->assertCount(0, $slots);
    }

    public function test_find_slots_in_range_returns_partial_continuous_window_when_day_is_excluded(): void
    {
        $this->createFullDayAvailability(['monday', 'tuesday']);

        // Window from Monday 00:00 → Tuesday 23:59:59, then Wednesday breaks.
        // Asking for 48h requires the window to be full 48h.
        // Monday 00:00 → Tuesday 23:59:59 is 48h minus 1 second.
        // Using a start at Monday 00:00 gives a window of 2 days (Monday +
        // Tuesday) which spans exactly 48h if we adjust to startOfDay.
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-17T00:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            48 * 60
        );

        $this->assertCount(1, $slots);
        $this->assertSame('2024-01-15T00:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-17T00:00:00Z', $slots->first()->getEnd()->getValue());
    }

    // ============================================================
    // SAME-DAY BEHAVIOUR STILL WORKS
    // ============================================================

    public function test_find_slots_for_day_still_works_with_hourly_availability(): void
    {
        $this->createAvailability('09:00:00', '12:00:00');

        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            DateTimeZuluVO::from('2024-01-15T00:00:00Z'),
            30
        );

        $this->assertCount(6, $slots);
        $this->assertSame('2024-01-15T09:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-15T11:30:00Z', $slots->last()->getStart()->getValue());
    }

    public function test_find_slots_for_day_respects_schedules_with_full_day_availability(): void
    {
        $availability = $this->createFullDayAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocker',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        $slots = $this->slotService->findSlotsForDay(
            $this->testCar,
            DateTimeZuluVO::from('2024-01-15T00:00:00Z'),
            60
        );

        $starts = $slots->map(fn ($slot) => $slot->getStart()->getValue())->toArray();

        $this->assertNotContains('2024-01-15T10:00:00Z', $starts);
        $this->assertNotContains('2024-01-15T11:00:00Z', $starts);
        $this->assertContains('2024-01-15T09:00:00Z', $starts);
        $this->assertContains('2024-01-15T12:00:00Z', $starts);
    }

    // ============================================================
    // CROSS-DAY EXPLICIT AVAILABILITY (dailyStart > dailyEnd)
    // ============================================================

    public function test_overnight_availability_produces_cross_day_window(): void
    {
        // 22:00 → 02:00 next day, every day.
        $this->createAvailability('22:00:00', '02:00:00', allDays: true);

        $start = DateTimeZuluVO::from('2024-01-15T22:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T02:00:00Z');

        $this->assertTrue(
            $this->slotService->isSlotAvailable($this->testCar, $start, $end)
        );
    }

    public function test_overnight_availabilities_merge_into_single_window(): void
    {
        // 22:00 → 02:00 next day → adjacent day starts at 22:00 again,
        // so windows do NOT merge by default (there's a 20h gap between
        // 02:00 and 22:00). Verify the window is exactly 4h.
        $this->createAvailability('22:00:00', '02:00:00', allDays: true);

        $start = DateTimeZuluVO::from('2024-01-15T22:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-16T06:00:00Z');

        $slots = $this->slotService->findSlotsInRange(
            $this->testCar,
            $start,
            $end,
            4 * 60
        );

        $this->assertCount(1, $slots);
        $this->assertSame('2024-01-15T22:00:00Z', $slots->first()->getStart()->getValue());
        $this->assertSame('2024-01-16T02:00:00Z', $slots->first()->getEnd()->getValue());
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * @param  string[]|null  $days
     */
    private function createFullDayAvailability(?array $days = null): Availability
    {
        $days = $days ?? [
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
        ];

        return $this->createAvailability('00:00:00', '23:59:59', $days);
    }

    /**
     * @param  string[]|null  $days
     */
    private function createAvailability(
        string $dailyStart,
        string $dailyEnd,
        ?array $days = null,
        bool $allDays = false,
    ): Availability {
        if ($allDays) {
            $days = [
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            ];
        }

        $days = $days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        $record = AvailabilityRecord::from([
            'name' => 'Test Availability',
            'type' => 'test',
            'days' => $days,
            'daily_start' => $dailyStart,
            'daily_end' => $dailyEnd,
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2025-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        return $this->availabilityService->create($record);
    }
}
