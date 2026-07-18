<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Repositories;

use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Support\Facades\DB;

final class AvailabilityRepositoryTest extends IntegrationTestCase
{
    private AvailabilityRepository $repository;

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

        $this->repository = $this->app->make(AvailabilityRepository::class);

        // Désactiver l'enforcement du service layer pour les tests
        $this->repository->withoutServiceEnforcement();
    }

    // ============================================================
    // CREATE TESTS
    // ============================================================

    public function test_can_create_availability(): void
    {
        $record = $this->createAvailabilityRecord();
        $availability = $this->repository->create($record);

        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Test Availability',
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);
    }

    public function test_can_create_availability_with_null_validity_dates(): void
    {
        $record = AvailabilityRecord::from([
            'name' => 'No Validity Dates',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $availability = $this->repository->create($record);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'No Validity Dates',
            'validity_start' => null,
            'validity_end' => null,
        ]);
    }

    public function test_can_create_raw_availability(): void
    {
        $data = [
            'name' => 'Raw Create',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01 00:00:00',
            'validity_end' => '2024-12-31 23:59:59',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ];

        $availability = $this->repository->createRaw($data);

        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Raw Create',
        ]);
    }

    // ============================================================
    // READ TESTS
    // ============================================================

    public function test_can_find_availability_by_id(): void
    {
        $availability = $this->createAvailability();
        $found = $this->repository->find($availability->id);

        $this->assertNotNull($found);
        $this->assertEquals($availability->id, $found->id);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find(99999);
        $this->assertNull($found);
    }

    public function test_can_find_with_trashed(): void
    {
        $availability = $this->createAvailability();
        $this->repository->delete($availability->id);

        $found = $this->repository->findWithTrashed($availability->id);

        $this->assertNotNull($found);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_can_find_by_schedulable(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $results = $this->repository->findBySchedulable(TestCar::class, 1);

        $this->assertCount(2, $results);
        $this->assertEquals($availability1->id, $results[0]->id);
    }

    public function test_can_find_by_day(): void
    {
        $availability = $this->createAvailability();

        $results = $this->repository->findByDay(TestCar::class, 1, WeekDay::MONDAY);

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_by_type(): void
    {
        $availability = $this->createAvailability();

        $results = $this->repository->findByType('test');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_active_at_date(): void
    {
        $availability = $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');

        $results = $this->repository->findActiveAtDate(TestCar::class, 1, $date);

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_active_in_date_range(): void
    {
        $availability = $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        $results = $this->repository->findActiveInDateRange(TestCar::class, 1, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_overlapping(): void
    {
        $availability = $this->createAvailability();

        $startTime = TimeZuluVO::from('10:00:00');
        $endTime = TimeZuluVO::from('12:00:00');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        $results = $this->repository->findOverlapping(
            TestCar::class,
            1,
            WeekDay::MONDAY,
            $startTime,
            $endTime,
            $validityStart,
            $validityEnd
        );

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_find_overlapping_excludes_given_id(): void
    {
        $availability = $this->createAvailability();

        $startTime = TimeZuluVO::from('10:00:00');
        $endTime = TimeZuluVO::from('12:00:00');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        $results = $this->repository->findOverlapping(
            TestCar::class,
            1,
            WeekDay::MONDAY,
            $startTime,
            $endTime,
            $validityStart,
            $validityEnd,
            $availability->id // Exclude self
        );

        $this->assertCount(0, $results);
    }

    public function test_can_find_cross_day_availabilities(): void
    {
        // Create a cross-day availability (starts at 22:00, ends at 06:00)
        $record = AvailabilityRecord::from([
            'name' => 'Cross Day',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '22:00:00',
            'daily_end' => '06:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);
        $this->repository->create($record);

        $results = $this->repository->findCrossDayAvailabilities(TestCar::class, 1);

        $this->assertCount(1, $results);
        $this->assertEquals('Cross Day', $results[0]->name);
    }

    public function test_can_find_short_durations(): void
    {
        // Create a short duration availability (less than 30 minutes)
        $record = AvailabilityRecord::from([
            'name' => 'Short Duration',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '09:15:00', // 15 minutes
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);
        $this->repository->create($record);

        $results = $this->repository->findShortDurations(TestCar::class, 1, 30);

        $this->assertCount(1, $results);
        $this->assertEquals('Short Duration', $results[0]->name);
    }

    public function test_can_find_invalid_date_ranges(): void
    {
        // Create an availability with invalid date range
        $record = AvailabilityRecord::from([
            'name' => 'Invalid Range',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '17:00:00',
            'daily_end' => '09:00:00', // start >= end
            'validity_start' => '2024-12-31T00:00:00Z',
            'validity_end' => '2024-01-01T23:59:59Z', // start >= end
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);
        $this->repository->create($record);

        $results = $this->repository->findInvalidDateRanges(TestCar::class, 1);

        $this->assertCount(1, $results);
        $this->assertEquals('Invalid Range', $results[0]->name);
    }

    public function test_schedulable_exists_returns_true_when_exists(): void
    {
        // Créer un TestCar avec une plaque unique
        ChronosMutationContext::withAllowed(function () {
            TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST456', // Plaque différente
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });

        $exists = $this->repository->schedulableExists(TestCar::class, 1);
        $this->assertTrue($exists);
    }

    public function test_schedulable_exists_returns_false_when_not_exists(): void
    {
        $exists = $this->repository->schedulableExists(TestCar::class, 99999);
        $this->assertFalse($exists);
    }

    public function test_schedulable_exists_returns_false_for_invalid_class(): void
    {
        $exists = $this->repository->schedulableExists('Invalid\\Class', 1);
        $this->assertFalse($exists);
    }

    public function test_get_schedulable_model_returns_class_when_exists(): void
    {
        $class = $this->repository->getSchedulableModel(TestCar::class);
        $this->assertEquals(TestCar::class, $class);
    }

    public function test_get_schedulable_model_returns_null_when_not_exists(): void
    {
        $class = $this->repository->getSchedulableModel('Invalid\\Class');
        $this->assertNull($class);
    }

    // ============================================================
    // UPDATE TESTS
    // ============================================================

    public function test_can_update_availability(): void
    {
        $availability = $this->createAvailability();

        $record = AvailabilityRecord::from([
            'name' => 'Updated Name',
            'type' => 'updated',
            'days' => ['monday', 'tuesday'],
            'daily_start' => '09:00:00',
            'daily_end' => '18:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $updated = $this->repository->update($availability->id, $record);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_update_raw_availability(): void
    {
        $availability = $this->createAvailability();

        $data = ['name' => 'Raw Updated'];

        $updated = $this->repository->updateRaw($availability->id, $data);

        $this->assertEquals('Raw Updated', $updated->name);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Raw Updated',
        ]);
    }

    // ============================================================
    // DELETE TESTS
    // ============================================================

    public function test_can_delete_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        $deleted = $this->repository->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_restore_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;
        $this->repository->delete($id);

        $restored = $this->repository->restore($id);

        $this->assertTrue($restored);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNull(Availability::find($id)->deleted_at);
    }

    public function test_can_force_delete_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        $deleted = $this->repository->forceDelete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $id]);
    }

    public function test_can_bulk_delete(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $criteria = AvailabilityRecord::from([
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $deleted = $this->repository->deleteBulk($criteria);

        $this->assertEquals(2, $deleted);
        $this->assertNotNull(Availability::withTrashed()->find($availability1->id)->deleted_at);
        $this->assertNotNull(Availability::withTrashed()->find($availability2->id)->deleted_at);
    }

    public function test_can_force_bulk_delete(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $criteria = AvailabilityRecord::from([
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $deleted = $this->repository->forceDeleteBulk($criteria);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $availability1->id]);
        $this->assertDatabaseMissing('availabilities', ['id' => $availability2->id]);
    }

    // ============================================================
    // COUNT TESTS
    // ============================================================

    public function test_can_count_availabilities(): void
    {
        $this->createAvailability();
        $this->createAvailability();

        $count = $this->repository->count();
        $this->assertEquals(2, $count);
    }

    public function test_can_count_with_criteria(): void
    {
        $this->createAvailability();

        $criteria = AvailabilityRecord::from([
            'type' => 'test',
        ]);

        $count = $this->repository->count($criteria);
        $this->assertEquals(1, $count);
    }

    public function test_can_check_exists(): void
    {
        $this->createAvailability();

        $criteria = AvailabilityRecord::from([
            'type' => 'test',
        ]);

        $exists = $this->repository->exists($criteria);
        $this->assertTrue($exists);
    }

    public function test_can_check_not_exists(): void
    {
        $criteria = AvailabilityRecord::from([
            'type' => 'nonexistent',
        ]);

        $exists = $this->repository->exists($criteria);
        $this->assertFalse($exists);
    }

    // ============================================================
    // FIND WITH FUTURE SCHEDULES TESTS
    // ============================================================

    public function test_find_with_future_schedules_returns_true(): void
    {
        $availability = $this->createAvailability();
        $now = DateTimeZuluVO::from('2024-01-15T09:00:00Z');

        // Create a future schedule (simulated)
        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
            'title' => 'Future Schedule',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 11:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hasFuture = $this->repository->findWithFutureSchedules($availability->id, $now);
        $this->assertTrue($hasFuture);
    }

    public function test_find_with_future_schedules_returns_false(): void
    {
        $availability = $this->createAvailability();
        $now = DateTimeZuluVO::from('2024-01-15T10:00:00Z');

        $hasFuture = $this->repository->findWithFutureSchedules($availability->id, $now);
        $this->assertFalse($hasFuture);
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function createAvailabilityRecord(): AvailabilityRecord
    {
        return AvailabilityRecord::from([
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
    }

    private function createAvailability(): Availability
    {
        $record = $this->createAvailabilityRecord();

        return $this->repository->create($record);
    }
}
