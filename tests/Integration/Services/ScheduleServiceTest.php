<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

final class ScheduleServiceTest extends IntegrationTestCase
{
    private ScheduleServiceInterface $scheduleService;

    private AvailabilityServiceInterface $availabilityService;

    private TestCar $testCar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testCar = ChronosMutationContext::withAllowed(function () {
            return TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST123',
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });

        $this->scheduleService = $this->app->make(ScheduleServiceInterface::class);
        $this->availabilityService = $this->app->make(AvailabilityServiceInterface::class);
    }

    // ============================================================
    // TESTS: FOR() METHOD
    // ============================================================

    public function test_for_method_sets_schedulable_context(): void
    {
        $result = $this->scheduleService->for($this->testCar);

        $this->assertInstanceOf(ScheduleServiceInterface::class, $result);
    }

    public function test_create_with_for_injects_schedulable_fields(): void
    {
        $availability = $this->createAvailability();

        $schedule = $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Test Schedule',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertEquals(TestCar::class, $schedule->schedulable_type);
        $this->assertEquals($this->testCar->id, $schedule->schedulable_id);
        $this->assertEquals('Test Schedule', $schedule->title);
    }

    public function test_create_with_for_auto_resets_context(): void
    {
        $availability = $this->createAvailability();

        $schedule1 = $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Schedule 1',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $this->assertEquals(TestCar::class, $schedule1->schedulable_type);

        $schedule2 = $this->scheduleService->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Schedule 2',
            'start_datetime' => '2024-01-15T11:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $this->assertInstanceOf(Schedule::class, $schedule2);
        $this->assertEquals('Schedule 2', $schedule2->title);
    }

    public function test_find_by_schedulable_without_parameter_uses_scoped_entity(): void
    {
        $availability = $this->createAvailability();

        $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Test Schedule',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $schedules = $this->scheduleService->for($this->testCar)->findBySchedulable();

        $this->assertCount(1, $schedules);
        $this->assertEquals('Test Schedule', $schedules->first()->title);
    }

    public function test_find_by_schedulable_with_explicit_entity_works(): void
    {
        $anotherCar = ChronosMutationContext::withAllowed(function () {
            return TestCar::create([
                'model' => 'Another Model',
                'license_plate' => 'TEST456',
                'type' => 'suv',
                'capacity' => 7,
            ]);
        });

        $anotherAvailability = $this->createAvailabilityFor($anotherCar);

        $this->scheduleService->for($anotherCar)->create(ScheduleRecord::from([
            'availability_id' => $anotherAvailability->id,
            'title' => 'Another Schedule',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $schedules = $this->scheduleService->for($this->testCar)->findBySchedulable($anotherCar);

        $this->assertCount(1, $schedules);
        $this->assertEquals('Another Schedule', $schedules->first()->title);
    }

    public function test_find_by_schedulable_without_entity_and_without_for_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No schedulable entity defined');

        $this->scheduleService->findBySchedulable();
    }

    public function test_find_with_for_filters_by_ownership(): void
    {
        $availability = $this->createAvailability();

        $schedule1 = $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Test Schedule 1',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $found = $this->scheduleService->for($this->testCar)->find($schedule1->id);
        $this->assertNotNull($found);
        $this->assertEquals($schedule1->id, $found->id);

        $notFound = $this->scheduleService->for($this->testCar)->find(99999);
        $this->assertNull($notFound);
    }

    public function test_find_with_for_returns_null_for_other_entity_schedule(): void
    {
        $anotherCar = ChronosMutationContext::withAllowed(function () {
            return TestCar::create([
                'model' => 'Another Model',
                'license_plate' => 'TEST456',
                'type' => 'suv',
                'capacity' => 7,
            ]);
        });

        $anotherAvailability = $this->createAvailabilityFor($anotherCar);

        $schedule2 = $this->scheduleService->for($anotherCar)->create(ScheduleRecord::from([
            'availability_id' => $anotherAvailability->id,
            'title' => 'Test Schedule 2',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $notFound = $this->scheduleService->for($this->testCar)->find($schedule2->id);
        $this->assertNull($notFound);
    }

    public function test_delete_with_for_verifies_ownership(): void
    {
        $availability = $this->createAvailability();

        $schedule = $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Test Schedule',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $deleted = $this->scheduleService->for($this->testCar)->delete($schedule->id);

        $this->assertTrue($deleted);
        $this->assertNotNull(Schedule::withTrashed()->find($schedule->id)->deleted_at);
    }

    public function test_cancel_with_for_verifies_ownership(): void
    {
        $availability = $this->createAvailability();

        $schedule = $this->scheduleService->for($this->testCar)->create(ScheduleRecord::from([
            'availability_id' => $availability->id,
            'title' => 'Test Schedule',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
            'status' => ScheduleStatus::BOOKED,
        ]));

        $cancelled = $this->scheduleService->for($this->testCar)->cancel($schedule->id);

        $this->assertEquals(ScheduleStatus::CANCELLED, $cancelled->status);
    }

    public function test_chained_methods_with_for_work_correctly(): void
    {
        $availability = $this->createAvailability();

        $schedule = $this->scheduleService
            ->for($this->testCar)
            ->create(ScheduleRecord::from([
                'availability_id' => $availability->id,
                'title' => 'Chain Test',
                'start_datetime' => '2024-01-15T10:00:00Z',
                'end_datetime' => '2024-01-15T11:00:00Z',
                'status' => ScheduleStatus::BOOKED,
            ]));

        $this->assertEquals(TestCar::class, $schedule->schedulable_type);
        $this->assertEquals($this->testCar->id, $schedule->schedulable_id);

        $updated = $this->scheduleService
            ->for($this->testCar)
            ->update($schedule->id, ScheduleRecord::from([
                'title' => 'Updated Chain Test',
            ]));

        $this->assertEquals('Updated Chain Test', $updated->title);

        $schedules = $this->scheduleService
            ->for($this->testCar)
            ->findBySchedulable();

        $this->assertCount(1, $schedules);
    }

    // ============================================================
    // TESTS: ORIGINAL METHODS
    // ============================================================

    public function test_can_create_schedule(): void
    {
        $availability = $this->createAvailability();

        $record = $this->createScheduleRecord($availability);
        $schedule = $this->scheduleService->create($record);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Test Schedule',
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'status' => ScheduleStatus::AVAILABLE->value,
        ]);
    }

    public function test_throws_validation_exception_when_schedule_overlaps(): void
    {
        $availability = $this->createAvailability();

        $record1 = $this->createScheduleRecord($availability);
        $this->scheduleService->create($record1);

        $record2 = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: $this->testCar->id,
            title: 'Overlapping Schedule',
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:15:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:45:00Z'),
        );

        $this->expectException(ValidationException::class);
        $this->scheduleService->create($record2);
    }

    public function test_can_update_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $record = ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Updated Title',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        $updated = $this->scheduleService->update($schedule->id, $record);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_throws_exception_when_updating_nonexistent_schedule(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $record = new ScheduleRecord;
        $this->scheduleService->update(99999, $record);
    }

    public function test_can_delete_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);
        $id = $schedule->id;

        $deleted = $this->scheduleService->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('schedules', ['id' => $id]);
        $this->assertNotNull(Schedule::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_find_schedule_by_id(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $found = $this->scheduleService->find($schedule->id);

        $this->assertNotNull($found);
        $this->assertEquals($schedule->id, $found->id);
    }

    public function test_find_by_availability_returns_schedules(): void
    {
        $availability = $this->createAvailability();

        $schedule1 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Schedule 1',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $schedule2 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Schedule 2',
                'start_datetime' => '2024-01-15 11:30:00',
                'end_datetime' => '2024-01-15 12:30:00',
            ]);
        });

        $results = $this->scheduleService->findByAvailability($availability->id);

        $this->assertCount(2, $results);
        $this->assertEquals($schedule1->id, $results[0]->id);
        $this->assertEquals($schedule2->id, $results[1]->id);
    }

    public function test_find_by_status_returns_schedules(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $results = $this->scheduleService->findByStatus(ScheduleStatus::AVAILABLE);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_find_by_date_returns_schedules(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);
        $date = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $results = $this->scheduleService->findByDate($date);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_search_by_title_returns_schedules(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $results = $this->scheduleService->searchByTitle('Test Schedule');

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_cancel_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $cancelled = $this->scheduleService->cancel($schedule->id);

        $this->assertEquals(ScheduleStatus::CANCELLED, $cancelled->status);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatus::CANCELLED->value,
        ]);
    }

    public function test_can_complete_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        ChronosMutationContext::withAllowed(function () use ($schedule) {
            $schedule->status = ScheduleStatus::BOOKED;
            $schedule->start_datetime = '2023-01-15 10:00:00';
            $schedule->end_datetime = '2023-01-15 11:00:00';
            $schedule->save();
        });

        $schedule->refresh();

        $completed = $this->scheduleService->complete($schedule->id);

        $this->assertEquals(ScheduleStatus::COMPLETED, $completed->status);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatus::COMPLETED->value,
        ]);
    }

    public function test_can_be_cancelled_returns_true_for_active_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $this->assertTrue($this->scheduleService->canBeCancelled($schedule));
    }

    private function createAvailability(): Availability
    {
        return $this->createAvailabilityFor($this->testCar);
    }

    private function createAvailabilityFor(TestCar $car): Availability
    {
        $record = AvailabilityRecord::from([
            'name' => 'Test Availability',
            'type' => 'test',
            'days' => WeekDayCollection::fromStrings(['monday', 'wednesday', 'friday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('17:00:00'),
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $car->id,
        ]);

        return $this->availabilityService->create($record);
    }

    private function createScheduleRecord(Availability $availability): ScheduleRecord
    {
        return ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Test Schedule',
            'description' => 'Test Description',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);
    }

    private function createSchedule(Availability $availability): Schedule
    {
        $record = $this->createScheduleRecord($availability);

        return $this->scheduleService->create($record);
    }
}
