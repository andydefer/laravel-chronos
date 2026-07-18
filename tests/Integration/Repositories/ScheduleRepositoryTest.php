<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Repositories;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Facades\DB;

final class ScheduleRepositoryTest extends IntegrationTestCase
{
    private ScheduleRepository $repository;

    private AvailabilityRepository $availabilityRepository;

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

        $this->repository = $this->app->make(ScheduleRepository::class);
        $this->availabilityRepository = $this->app->make(AvailabilityRepository::class);

        // Désactiver l'enforcement du service layer pour les tests
        $this->repository->withoutServiceEnforcement();
        $this->availabilityRepository->withoutServiceEnforcement();
    }

    // ============================================================
    // CREATE TESTS
    // ============================================================

    public function test_can_create_schedule(): void
    {
        $availability = $this->createAvailability();

        $record = $this->createScheduleRecord($availability);
        $schedule = $this->repository->create($record);

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

    public function test_can_create_schedule_with_different_status(): void
    {
        $availability = $this->createAvailability();

        $record = ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Completed Schedule',
            'status' => ScheduleStatus::COMPLETED,
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        $schedule = $this->repository->create($record);

        $this->assertEquals(ScheduleStatus::COMPLETED, $schedule->status);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatus::COMPLETED->value,
        ]);
    }

    public function test_can_create_raw_schedule(): void
    {
        $availability = $this->createAvailability();

        $data = [
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Raw Schedule',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 11:00:00',
        ];

        $schedule = $this->repository->createRaw($data);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Raw Schedule',
        ]);
    }

    // ============================================================
    // READ TESTS
    // ============================================================

    public function test_can_find_schedule_by_id(): void
    {
        $schedule = $this->createSchedule();
        $found = $this->repository->find($schedule->id);

        $this->assertNotNull($found);
        $this->assertEquals($schedule->id, $found->id);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find(99999);
        $this->assertNull($found);
    }

    public function test_can_find_by_availability(): void
    {
        $availability = $this->createAvailability();
        $schedule1 = $this->createSchedule($availability);
        $schedule2 = $this->createSchedule($availability);

        $results = $this->repository->findByAvailability($availability->id);

        $this->assertCount(2, $results);
        $this->assertEquals($schedule1->id, $results[0]->id);
    }

    public function test_can_find_by_schedulable(): void
    {
        $schedule1 = $this->createSchedule();
        $schedule2 = $this->createSchedule();

        $results = $this->repository->findBySchedulable($this->testCar);

        $this->assertCount(2, $results);
        $this->assertEquals($schedule1->id, $results[0]->id);
    }

    public function test_can_find_by_status(): void
    {
        $schedule = $this->createSchedule();

        $results = $this->repository->findByStatus(ScheduleStatus::AVAILABLE);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_by_status_with_availability_filter(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $schedule1 = $this->createSchedule($availability1);
        $schedule2 = $this->createSchedule($availability2);

        $results = $this->repository->findByStatus(ScheduleStatus::AVAILABLE, $availability1->id);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule1->id, $results[0]->id);
    }

    public function test_can_search_by_title(): void
    {
        $schedule = $this->createSchedule();

        $results = $this->repository->searchByTitle('Test Schedule');

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_search_by_title_with_availability_filter(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $schedule1 = $this->createSchedule($availability1);
        $schedule2 = $this->createSchedule($availability2);

        $results = $this->repository->searchByTitle('Test Schedule', $availability1->id);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule1->id, $results[0]->id);
    }

    public function test_can_find_by_date(): void
    {
        $schedule = $this->createSchedule();
        $date = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $results = $this->repository->findByDate($date);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_in_date_range(): void
    {
        $schedule = $this->createSchedule();
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');

        $results = $this->repository->findInDateRange($start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_by_availability_in_date_range(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');

        $results = $this->repository->findByAvailabilityInDateRange($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_by_day_of_week(): void
    {
        $schedule = $this->createSchedule();

        $results = $this->repository->findByDayOfWeek(1);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_overlapping(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        $results = $this->repository->findOverlapping($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_find_overlapping_excludes_given_id(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        $results = $this->repository->findOverlapping($availability->id, $start, $end, $schedule->id);

        $this->assertCount(0, $results);
    }

    public function test_can_find_conflicting(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        $results = $this->repository->findConflicting($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($schedule->id, $results[0]->id);
    }

    public function test_can_find_with_invalid_chronology(): void
    {
        $availability = $this->createAvailability();

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Invalid Chronology',
            'start_datetime' => '2024-01-15 11:00:00',
            'end_datetime' => '2024-01-15 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findWithInvalidChronology();

        $this->assertCount(1, $results);
        $this->assertEquals('Invalid Chronology', $results[0]->title);
    }

    public function test_can_find_with_exceeding_duration(): void
    {
        $availability = $this->createAvailability();

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Long Schedule',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Short Schedule',
            'start_datetime' => '2024-01-15 13:00:00',
            'end_datetime' => '2024-01-15 13:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findWithExceedingDuration($availability->id, 60);

        $this->assertCount(1, $results);
        $this->assertEquals('Long Schedule', $results[0]->title);
    }

    public function test_can_find_violating_buffer_time(): void
    {
        $availability = $this->createAvailability();

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Schedule 1',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 11:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Schedule 2',
            'start_datetime' => '2024-01-15 11:15:00',
            'end_datetime' => '2024-01-15 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findViolatingBufferTime($availability->id, 30);

        $this->assertCount(1, $results);
        $this->assertEquals('Schedule 1', $results[0]->title);
    }

    public function test_has_cross_day_schedule_returns_true(): void
    {
        $availability = $this->createAvailability();

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Cross Day Schedule',
            'start_datetime' => '2024-01-15 22:00:00',
            'end_datetime' => '2024-01-16 02:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hasCrossDay = $this->repository->hasCrossDaySchedule($availability->id);
        $this->assertTrue($hasCrossDay);
    }

    public function test_has_cross_day_schedule_returns_false(): void
    {
        $availability = $this->createAvailability();
        $this->createSchedule($availability);

        $hasCrossDay = $this->repository->hasCrossDaySchedule($availability->id);
        $this->assertFalse($hasCrossDay);
    }

    // ============================================================
    // UPDATE TESTS
    // ============================================================

    public function test_can_update_schedule(): void
    {
        $schedule = $this->createSchedule();

        $record = ScheduleRecord::from([
            'availability_id' => $schedule->availability_id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => ScheduleStatus::COMPLETED,
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        $updated = $this->repository->update($schedule->id, $record);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals(ScheduleStatus::COMPLETED, $updated->status);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Updated Title',
            'status' => ScheduleStatus::COMPLETED->value,
        ]);
    }

    public function test_can_update_raw_schedule(): void
    {
        $schedule = $this->createSchedule();

        $data = ['title' => 'Raw Updated'];

        $updated = $this->repository->updateRaw($schedule->id, $data);

        $this->assertEquals('Raw Updated', $updated->title);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Raw Updated',
        ]);
    }

    // ============================================================
    // DELETE TESTS
    // ============================================================

    public function test_can_delete_schedule(): void
    {
        $schedule = $this->createSchedule();
        $id = $schedule->id;

        $deleted = $this->repository->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('schedules', ['id' => $id]);
        $this->assertNotNull(Schedule::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_restore_schedule(): void
    {
        $schedule = $this->createSchedule();
        $id = $schedule->id;
        $this->repository->delete($id);

        $restored = $this->repository->restore($id);

        $this->assertTrue($restored);
        $this->assertDatabaseHas('schedules', ['id' => $id]);
        $this->assertNull(Schedule::find($id)->deleted_at);
    }

    public function test_can_force_delete_schedule(): void
    {
        $schedule = $this->createSchedule();
        $id = $schedule->id;

        $deleted = $this->repository->forceDelete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('schedules', ['id' => $id]);
    }

    public function test_can_bulk_delete_schedules(): void
    {
        $availability = $this->createAvailability();
        $schedule1 = $this->createSchedule($availability);
        $schedule2 = $this->createSchedule($availability);

        $criteria = ScheduleRecord::from([
            'availability_id' => $availability->id,
        ]);

        $deleted = $this->repository->deleteBulk($criteria);

        $this->assertEquals(2, $deleted);
        $this->assertNotNull(Schedule::withTrashed()->find($schedule1->id)->deleted_at);
        $this->assertNotNull(Schedule::withTrashed()->find($schedule2->id)->deleted_at);
    }

    public function test_can_force_bulk_delete_schedules(): void
    {
        $availability = $this->createAvailability();
        $schedule1 = $this->createSchedule($availability);
        $schedule2 = $this->createSchedule($availability);

        $criteria = ScheduleRecord::from([
            'availability_id' => $availability->id,
        ]);

        $deleted = $this->repository->forceDeleteBulk($criteria);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule1->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule2->id]);
    }

    // ============================================================
    // COUNT & EXISTS TESTS
    // ============================================================

    public function test_can_count_schedules(): void
    {
        $this->createSchedule();
        $this->createSchedule();

        $count = $this->repository->count();
        $this->assertEquals(2, $count);
    }

    public function test_can_count_with_criteria(): void
    {
        $availability = $this->createAvailability();
        $this->createSchedule($availability);

        $criteria = ScheduleRecord::from([
            'availability_id' => $availability->id,
        ]);

        $count = $this->repository->count($criteria);
        $this->assertEquals(1, $count);
    }

    public function test_can_check_exists(): void
    {
        $availability = $this->createAvailability();
        $this->createSchedule($availability);

        $criteria = ScheduleRecord::from([
            'availability_id' => $availability->id,
        ]);

        $exists = $this->repository->exists($criteria);
        $this->assertTrue($exists);
    }

    public function test_can_check_not_exists(): void
    {
        $criteria = ScheduleRecord::from([
            'availability_id' => 99999,
        ]);

        $exists = $this->repository->exists($criteria);
        $this->assertFalse($exists);
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

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
            'schedulable_id' => $this->testCar->id,
        ]);

        return $this->availabilityRepository->create($record);
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

    private function createSchedule(?Availability $availability = null): Schedule
    {
        if ($availability === null) {
            $availability = $this->createAvailability();
        }

        $record = $this->createScheduleRecord($availability);

        return $this->repository->create($record);
    }
}
