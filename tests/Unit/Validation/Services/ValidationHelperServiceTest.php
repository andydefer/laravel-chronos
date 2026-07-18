<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Services;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Support\Collection;

final class ValidationHelperServiceTest extends IntegrationTestCase
{
    private ValidationHelperService $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new ValidationHelperService;

        ChronosMutationContext::withAllowed(function () {
            TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST123',
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });
    }

    public function test_time_slots_overlap_returns_true_when_overlapping(): void
    {
        // Arrange
        $start1 = TimeZuluVO::from('09:00:00');
        $end1 = TimeZuluVO::from('10:00:00');
        $start2 = TimeZuluVO::from('09:30:00');
        $end2 = TimeZuluVO::from('10:30:00');

        // Act
        $result = $this->helper->timeSlotsOverlap($start1, $end1, $start2, $end2);

        // Assert
        $this->assertTrue($result);
    }

    public function test_time_slots_overlap_returns_false_when_not_overlapping(): void
    {
        // Arrange
        $start1 = TimeZuluVO::from('09:00:00');
        $end1 = TimeZuluVO::from('10:00:00');
        $start2 = TimeZuluVO::from('10:00:00');
        $end2 = TimeZuluVO::from('11:00:00');

        // Act
        $result = $this->helper->timeSlotsOverlap($start1, $end1, $start2, $end2);

        // Assert
        $this->assertFalse($result);
    }

    public function test_date_ranges_overlap_returns_true_when_overlapping(): void
    {
        // Arrange
        $start1 = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end1 = DateTimeZuluVO::from('2024-01-10T00:00:00Z');
        $start2 = DateTimeZuluVO::from('2024-01-05T00:00:00Z');
        $end2 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $this->helper->dateRangesOverlap($start1, $end1, $start2, $end2);

        // Assert
        $this->assertTrue($result);
    }

    public function test_date_ranges_overlap_returns_false_when_not_overlapping(): void
    {
        // Arrange
        $start1 = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end1 = DateTimeZuluVO::from('2024-01-10T00:00:00Z');
        $start2 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end2 = DateTimeZuluVO::from('2024-01-20T00:00:00Z');

        // Act
        $result = $this->helper->dateRangesOverlap($start1, $end1, $start2, $end2);

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_days_from_availability_record_returns_days_array(): void
    {
        // Arrange
        $weekDays = WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday']);
        $record = AvailabilityRecord::from([
            'days' => $weekDays,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        // Act
        $days = $this->helper->getDays($record);

        // Assert
        $this->assertIsArray($days);
        $this->assertCount(3, $days);
        $this->assertContains('monday', $days);
        $this->assertContains('tuesday', $days);
        $this->assertContains('wednesday', $days);
    }

    public function test_get_days_from_availability_model_returns_days_array(): void
    {
        // Arrange
        $availability = new Availability;
        $availability->days = ['monday', 'friday'];

        // Act
        $days = $this->helper->getDays($availability);

        // Assert
        $this->assertIsArray($days);
        $this->assertCount(2, $days);
        $this->assertContains('monday', $days);
        $this->assertContains('friday', $days);
    }

    public function test_get_validity_start_from_record(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        // Act
        $result = $this->helper->getValidityStart($record);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('2024-01-01T00:00:00Z', $result->getValue());
    }

    public function test_get_validity_end_from_record(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        // Act
        $result = $this->helper->getValidityEnd($record);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('2024-12-31T23:59:59Z', $result->getValue());
    }

    public function test_get_daily_start_from_record(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        // Act
        $result = $this->helper->getDailyStart($record);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('09:00:00', $result->toTimeString());
    }

    public function test_get_daily_end_from_record(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'daily_end' => TimeZuluVO::from('17:00:00'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        // Act
        $result = $this->helper->getDailyEnd($record);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('17:00:00', $result->toTimeString());
    }

    public function test_is_within_validity_period_returns_true_when_within(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $result = $this->helper->isWithinValidityPeriod($date, $validityStart, $validityEnd);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_within_validity_period_returns_false_when_before_start(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2023-12-31T12:00:00Z');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $result = $this->helper->isWithinValidityPeriod($date, $validityStart, $validityEnd);

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_within_validity_period_returns_false_when_after_end(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2025-01-01T12:00:00Z');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $result = $this->helper->isWithinValidityPeriod($date, $validityStart, $validityEnd);

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_within_validity_period_returns_true_when_no_dates_defined(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');

        // Act
        $result = $this->helper->isWithinValidityPeriod($date, null, null);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_day_in_array_returns_true_when_day_exists(): void
    {
        // Arrange
        $day = WeekDay::MONDAY;
        $days = ['monday', 'tuesday', 'wednesday'];

        // Act
        $result = $this->helper->isDayInArray($day, $days);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_day_in_array_returns_false_when_day_does_not_exist(): void
    {
        // Arrange
        $day = WeekDay::FRIDAY;
        $days = ['monday', 'tuesday', 'wednesday'];

        // Act
        $result = $this->helper->isDayInArray($day, $days);

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_conflicting_schedules_returns_empty_collection_when_no_conflicts(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:00:00Z');

        // Act
        $conflicts = $this->helper->getConflictingSchedules($availability->id, $start, $end);

        // Assert
        $this->assertInstanceOf(Collection::class, $conflicts);
        $this->assertCount(0, $conflicts);
    }

    public function test_get_conflicting_schedules_returns_conflicts_when_exist(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Test Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T10:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');

        // Act
        $conflicts = $this->helper->getConflictingSchedules($availability->id, $start, $end);

        // Assert
        $this->assertCount(1, $conflicts);
        $this->assertSame('Test Schedule', $conflicts->first()->title);
    }

    public function test_get_conflicting_schedules_excludes_specified_id(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Test Schedule 1',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);

            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Test Schedule 2',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:30:00',
            ]);
        });

        $start = DateTimeZuluVO::from('2024-01-15T10:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');

        // Act
        $conflicts = $this->helper->getConflictingSchedules(
            $availability->id,
            $start,
            $end,
            $availability->id
        );

        // Assert
        $this->assertCount(1, $conflicts);
        $this->assertSame('Test Schedule 2', $conflicts->first()->title);
    }

    public function test_get_next_schedule_returns_next_schedule(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 1',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 2',
                'start_datetime' => '2024-01-15 11:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        $after = DateTimeZuluVO::from('2024-01-15T10:00:00Z');

        // Act
        $next = $this->helper->getNextSchedule($availability->id, $after);

        // Assert
        $this->assertNotNull($next);
        $this->assertSame('Schedule 2', $next->title);
        $this->assertSame('2024-01-15 11:00:00', $next->start_datetime->format('Y-m-d H:i:s'));
    }

    public function test_get_previous_schedule_returns_previous_schedule(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 1',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 2',
                'start_datetime' => '2024-01-15 11:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        $before = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        // Act
        $previous = $this->helper->getPreviousSchedule($availability->id, $before);

        // Assert
        $this->assertNotNull($previous);
        $this->assertSame('Schedule 1', $previous->title);
        $this->assertSame('2024-01-15 09:00:00', $previous->start_datetime->format('Y-m-d H:i:s'));
    }

    public function test_get_duration_in_minutes_returns_correct_duration(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        // Act
        $duration = $this->helper->getDurationInMinutes($start, $end);

        // Assert
        $this->assertSame(90, $duration);
    }

    public function test_get_time_duration_in_minutes_returns_correct_duration(): void
    {
        // Arrange
        $start = TimeZuluVO::from('09:00:00');
        $end = TimeZuluVO::from('10:30:00');

        // Act
        $duration = $this->helper->getTimeDurationInMinutes($start, $end);

        // Assert
        $this->assertSame(90, $duration);
    }

    public function test_is_within_daily_bounds_returns_true_when_within(): void
    {
        // Arrange
        $dateTime = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $dailyStart = TimeZuluVO::from('09:00:00');
        $dailyEnd = TimeZuluVO::from('17:00:00');

        // Act
        $result = $this->helper->isWithinDailyBounds($dateTime, $dailyStart, $dailyEnd);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_within_daily_bounds_returns_false_when_outside(): void
    {
        // Arrange
        $dateTime = DateTimeZuluVO::from('2024-01-15T08:30:00Z');
        $dailyStart = TimeZuluVO::from('09:00:00');
        $dailyEnd = TimeZuluVO::from('17:00:00');

        // Act
        $result = $this->helper->isWithinDailyBounds($dateTime, $dailyStart, $dailyEnd);

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_within_daily_bounds_supports_cross_day(): void
    {
        // Arrange
        $dateTime = DateTimeZuluVO::from('2024-01-15T23:30:00Z');
        $dailyStart = TimeZuluVO::from('22:00:00');
        $dailyEnd = TimeZuluVO::from('06:00:00');

        // Act
        $result = $this->helper->isWithinDailyBounds($dateTime, $dailyStart, $dailyEnd);

        // Assert
        $this->assertTrue($result);
    }

    public function test_get_days_in_range_returns_all_days_in_range(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-17T00:00:00Z');

        // Act
        $days = $this->helper->getDaysInRange($start, $end);

        // Assert
        $this->assertCount(3, $days);
        $this->assertContains('monday', $days);
        $this->assertContains('tuesday', $days);
        $this->assertContains('wednesday', $days);
    }

    public function test_all_days_in_range_are_allowed_returns_true_when_all_allowed(): void
    {
        // Arrange
        $rangeDays = ['monday', 'tuesday', 'wednesday'];
        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        // Act
        $result = $this->helper->allDaysInRangeAreAllowed($rangeDays, $allowedDays);

        // Assert
        $this->assertTrue($result);
    }

    public function test_all_days_in_range_are_allowed_returns_false_when_not_all_allowed(): void
    {
        // Arrange
        $rangeDays = ['monday', 'tuesday', 'saturday'];
        $allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        // Act
        $result = $this->helper->allDaysInRangeAreAllowed($rangeDays, $allowedDays);

        // Assert
        $this->assertFalse($result);
    }

    private function createAvailability(): Availability
    {
        return ChronosMutationContext::withAllowed(function () {
            $record = AvailabilityRecord::from([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            $service = $this->app->make(AvailabilityServiceInterface::class);

            return $service->create($record);
        });
    }
}
