<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

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

final class ScheduleServiceTest extends IntegrationTestCase
{
    private ScheduleServiceInterface $scheduleService;

    private AvailabilityServiceInterface $availabilityService;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un TestCar pour les tests
        ChronosMutationContext::withAllowed(function () {
            TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST123',
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });

        $this->scheduleService = $this->app->make(ScheduleServiceInterface::class);
        $this->availabilityService = $this->app->make(AvailabilityServiceInterface::class);
    }

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
            'schedulable_id' => 1,
            'status' => ScheduleStatus::AVAILABLE->value,
        ]);
    }

    public function test_throws_validation_exception_when_schedule_overlaps(): void
    {
        $availability = $this->createAvailability();

        // Create first schedule
        $record1 = $this->createScheduleRecord($availability);
        $this->scheduleService->create($record1);

        // Create overlapping schedule
        $record2 = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
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
            'schedulable_id' => 1,
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

        // Avec soft delete, le record existe encore avec deleted_at non null
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

        // Créer deux schedules avec des dates différentes pour éviter le conflit
        $schedule1 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 1',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $schedule2 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Schedule 2',
                'start_datetime' => '2024-01-15 11:30:00', // Différent
                'end_datetime' => '2024-01-15 12:30:00', // Différent
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

        // Le schedule doit être BOOKED et dans le passé pour être complété
        ChronosMutationContext::withAllowed(function () use ($schedule) {
            $schedule->status = ScheduleStatus::BOOKED;
            $schedule->start_datetime = '2023-01-15 10:00:00';
            $schedule->end_datetime = '2023-01-15 11:00:00';
            $schedule->save();
        });

        // Rafraîchir le schedule pour avoir les données mises à jour
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
        $record = AvailabilityRecord::from([
            'name' => 'Test Availability',
            'type' => 'test',
            'days' => ['monday', 'wednesday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        return $this->availabilityService->create($record);
    }

    private function createScheduleRecord(Availability $availability): ScheduleRecord
    {
        return ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
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
