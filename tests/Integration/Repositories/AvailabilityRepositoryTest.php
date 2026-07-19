<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Repositories;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
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

        $this->repository = $this->app->make(AvailabilityRepository::class);
        $this->repository->withoutServiceEnforcement();
    }

    // ============================================================
    // CREATE TESTS
    // ============================================================

    public function test_can_create_availability(): void
    {
        // Arrange
        $record = $this->createAvailabilityRecord();

        // Act
        $availability = $this->repository->create($record);

        // Assert
        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Test Availability',
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
    }

    public function test_can_create_availability_with_null_validity_dates(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'name' => 'No Validity Dates',
            'type' => 'test',
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('17:00:00'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        // Act
        $availability = $this->repository->create($record);

        // Assert
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'No Validity Dates',
            'validity_start' => null,
            'validity_end' => null,
        ]);
    }

    public function test_can_create_raw_availability(): void
    {
        // Arrange
        $data = [
            'name' => 'Raw Create',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01 00:00:00',
            'validity_end' => '2024-12-31 23:59:59',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ];

        // Act
        $availability = $this->repository->createRaw($data);

        // Assert
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
        // Arrange
        $availability = $this->createAvailability();

        // Act
        $found = $this->repository->find($availability->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($availability->id, $found->id);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    public function test_can_find_with_trashed(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $this->repository->delete($availability->id);

        // Act
        $found = $this->repository->findWithTrashed($availability->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertNotNull($found->deleted_at);
    }

    public function test_can_find_by_schedulable(): void
    {
        // Arrange
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        // Act
        $results = $this->repository->findBySchedulable($this->testCar);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals($availability1->id, $results[0]->id);
    }

    public function test_can_find_by_schedulable_with_limit(): void
    {
        // Arrange - Create 5 availabilities
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findBySchedulable($this->testCar, 3);

        // Assert
        $this->assertCount(3, $results);
        $this->assertEquals('Availability 1', $results->first()->name);
        $this->assertEquals('Availability 3', $results->last()->name);
    }

    public function test_can_find_by_day(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        // Act
        $results = $this->repository->findByDay($this->testCar, WeekDay::MONDAY);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_by_day_with_limit(): void
    {
        // Arrange - Create 5 availabilities with Monday
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findByDay($this->testCar, WeekDay::MONDAY, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_by_type(): void
    {
        // Arrange
        $availability = $this->createAvailability();

        // Act
        $results = $this->repository->findByType('test');

        // Assert
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_by_type_with_limit(): void
    {
        // Arrange - Create 5 availabilities with type 'test'
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findByType('test', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_active_at_date(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');

        // Act
        $results = $this->repository->findActiveAtDate($this->testCar, $date);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_active_at_date_with_limit(): void
    {
        // Arrange - Create 5 availabilities active on date
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findActiveAtDate($this->testCar, $date, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_active_in_date_range(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $results = $this->repository->findActiveInDateRange($this->testCar, $start, $end);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_can_find_active_in_date_range_with_limit(): void
    {
        // Arrange - Create 5 availabilities in date range
        $start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-12-31T23:59:59Z');
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findActiveInDateRange($this->testCar, $start, $end, null, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_overlapping(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $startTime = TimeZuluVO::from('10:00:00');
        $endTime = TimeZuluVO::from('12:00:00');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $results = $this->repository->findOverlapping(
            $this->testCar,
            WeekDay::MONDAY,
            $startTime,
            $endTime,
            $validityStart,
            $validityEnd
        );

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_find_overlapping_with_limit(): void
    {
        // Arrange - Create 5 overlapping availabilities
        $startTime = TimeZuluVO::from('10:00:00');
        $endTime = TimeZuluVO::from('12:00:00');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('17:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findOverlapping(
            $this->testCar,
            WeekDay::MONDAY,
            $startTime,
            $endTime,
            $validityStart,
            $validityEnd,
            null,
            3
        );

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_find_overlapping_excludes_given_id(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $startTime = TimeZuluVO::from('10:00:00');
        $endTime = TimeZuluVO::from('12:00:00');
        $validityStart = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $validityEnd = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        // Act
        $results = $this->repository->findOverlapping(
            $this->testCar,
            WeekDay::MONDAY,
            $startTime,
            $endTime,
            $validityStart,
            $validityEnd,
            $availability->id
        );

        // Assert
        $this->assertCount(0, $results);
    }

    public function test_can_find_cross_day_availabilities(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'name' => 'Cross Day',
            'type' => 'test',
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('22:00:00'),
            'daily_end' => TimeZuluVO::from('06:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
        $this->repository->create($record);

        // Act
        $results = $this->repository->findCrossDayAvailabilities($this->testCar);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Cross Day', $results[0]->name);
    }

    public function test_can_find_cross_day_availabilities_with_limit(): void
    {
        // Arrange - Create 5 cross-day availabilities
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Cross Day $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('22:00:00'),
                'daily_end' => TimeZuluVO::from('06:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findCrossDayAvailabilities($this->testCar, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_short_durations(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'name' => 'Short Duration',
            'type' => 'test',
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('09:15:00'),
            'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
        $this->repository->create($record);

        // Act
        $results = $this->repository->findShortDurations($this->testCar, 30);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Short Duration', $results[0]->name);
    }

    public function test_can_find_short_durations_with_limit(): void
    {
        // Arrange - Create 5 short duration availabilities
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Short Duration $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('09:00:00'),
                'daily_end' => TimeZuluVO::from('09:15:00'),
                'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findShortDurations($this->testCar, 30, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_can_find_invalid_date_ranges(): void
    {
        // Arrange
        $record = AvailabilityRecord::from([
            'name' => 'Invalid Range',
            'type' => 'test',
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('17:00:00'),
            'daily_end' => TimeZuluVO::from('09:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-12-31T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-01-01T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
        $this->repository->create($record);

        // Act
        $results = $this->repository->findInvalidDateRanges($this->testCar);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Invalid Range', $results[0]->name);
    }

    public function test_can_find_invalid_date_ranges_with_limit(): void
    {
        // Arrange - Create 5 invalid date range availabilities
        for ($i = 1; $i <= 5; $i++) {
            $record = AvailabilityRecord::from([
                'name' => "Invalid Range $i",
                'type' => 'test',
                'days' => WeekDayCollection::fromStrings(['monday']),
                'daily_start' => TimeZuluVO::from('17:00:00'),
                'daily_end' => TimeZuluVO::from('09:00:00'),
                'validity_start' => DateTimeZuluVO::from('2024-12-31T00:00:00Z'),
                'validity_end' => DateTimeZuluVO::from('2024-01-01T23:59:59Z'),
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
            ]);
            $this->repository->create($record);
        }

        // Act
        $results = $this->repository->findInvalidDateRanges($this->testCar, 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_schedulable_exists_returns_true_when_exists(): void
    {
        // Act
        $exists = $this->repository->schedulableExists($this->testCar);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_schedulable_exists_returns_false_when_not_exists(): void
    {
        // Arrange
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        // Act
        $exists = $this->repository->schedulableExists($unknownCar);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_get_schedulable_model_returns_class_when_exists(): void
    {
        // Act
        $class = $this->repository->getSchedulableModel($this->testCar);

        // Assert
        $this->assertEquals(TestCar::class, $class);
    }

    public function test_get_schedulable_model_returns_null_when_not_exists(): void
    {
        // Arrange
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        // Act
        $class = $this->repository->getSchedulableModel($unknownCar);

        // Assert
        $this->assertNull($class);
    }

    // ============================================================
    // UPDATE TESTS
    // ============================================================

    public function test_can_update_availability(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $record = AvailabilityRecord::from([
            'name' => 'Updated Name',
            'type' => 'updated',
            'days' => WeekDayCollection::fromStrings(['monday', 'tuesday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('18:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        // Act
        $updated = $this->repository->update($availability->id, $record);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_update_raw_availability(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $data = ['name' => 'Raw Updated'];

        // Act
        $updated = $this->repository->updateRaw($availability->id, $data);

        // Assert
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
        // Arrange
        $availability = $this->createAvailability();
        $id = $availability->id;

        // Act
        $deleted = $this->repository->delete($id);

        // Assert
        $this->assertTrue($deleted);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_restore_availability(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $id = $availability->id;
        $this->repository->delete($id);

        // Act
        $restored = $this->repository->restore($id);

        // Assert
        $this->assertTrue($restored);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNull(Availability::find($id)->deleted_at);
    }

    public function test_can_force_delete_availability(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $id = $availability->id;

        // Act
        $deleted = $this->repository->forceDelete($id);

        // Assert
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $id]);
    }

    public function test_can_bulk_delete(): void
    {
        // Arrange
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();
        $criteria = AvailabilityRecord::from([
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        // Act
        $deleted = $this->repository->deleteBulk($criteria);

        // Assert
        $this->assertEquals(2, $deleted);
        $this->assertNotNull(Availability::withTrashed()->find($availability1->id)->deleted_at);
        $this->assertNotNull(Availability::withTrashed()->find($availability2->id)->deleted_at);
    }

    public function test_can_force_bulk_delete(): void
    {
        // Arrange
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();
        $criteria = AvailabilityRecord::from([
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        // Act
        $deleted = $this->repository->forceDeleteBulk($criteria);

        // Assert
        $this->assertEquals(2, $deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $availability1->id]);
        $this->assertDatabaseMissing('availabilities', ['id' => $availability2->id]);
    }

    // ============================================================
    // COUNT TESTS
    // ============================================================

    public function test_can_count_availabilities(): void
    {
        // Arrange
        $this->createAvailability();
        $this->createAvailability();

        // Act
        $count = $this->repository->count();

        // Assert
        $this->assertEquals(2, $count);
    }

    public function test_can_count_with_criteria(): void
    {
        // Arrange
        $this->createAvailability();
        $criteria = AvailabilityRecord::from([
            'type' => 'test',
        ]);

        // Act
        $count = $this->repository->count($criteria);

        // Assert
        $this->assertEquals(1, $count);
    }

    public function test_can_check_exists(): void
    {
        // Arrange
        $this->createAvailability();
        $criteria = AvailabilityRecord::from([
            'type' => 'test',
        ]);

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_can_check_not_exists(): void
    {
        // Arrange
        $criteria = AvailabilityRecord::from([
            'type' => 'nonexistent',
        ]);

        // Act
        $exists = $this->repository->exists($criteria);

        // Assert
        $this->assertFalse($exists);
    }

    // ============================================================
    // FIND WITH FUTURE SCHEDULES TESTS
    // ============================================================

    public function test_find_with_future_schedules_returns_true(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $now = DateTimeZuluVO::from('2024-01-15T09:00:00Z');

        DB::table('schedules')->insert([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
            'title' => 'Future Schedule',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 11:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Act
        $hasFuture = $this->repository->findWithFutureSchedules($availability->id, $now);

        // Assert
        $this->assertTrue($hasFuture);
    }

    public function test_find_with_future_schedules_returns_false(): void
    {
        // Arrange
        $availability = $this->createAvailability();
        $now = DateTimeZuluVO::from('2024-01-15T10:00:00Z');

        // Act
        $hasFuture = $this->repository->findWithFutureSchedules($availability->id, $now);

        // Assert
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
            'days' => WeekDayCollection::fromStrings(['monday', 'wednesday', 'friday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('17:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
    }

    private function createAvailability(): Availability
    {
        $record = $this->createAvailabilityRecord();

        return $this->repository->create($record);
    }
}
