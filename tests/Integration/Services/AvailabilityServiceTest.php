<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Facades\DB;

final class AvailabilityServiceTest extends IntegrationTestCase
{
    private AvailabilityServiceInterface $service;

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

        $this->service = $this->app->make(AvailabilityServiceInterface::class);
    }

    // ============================================================
    // TESTS: FOR() METHOD
    // ============================================================

    public function test_for_method_sets_schedulable_context(): void
    {
        $result = $this->service->for($this->testCar);

        $this->assertInstanceOf(AvailabilityServiceInterface::class, $result);
    }

    public function test_create_with_for_injects_schedulable_fields(): void
    {
        $availability = $this->service->for($this->testCar)->create(AvailabilityRecord::from([
            'name' => 'Test Availability',
            'type' => 'test',
            'days' => ['monday', 'wednesday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertEquals(TestCar::class, $availability->schedulable_type);
        $this->assertEquals($this->testCar->id, $availability->schedulable_id);
        $this->assertEquals('Test Availability', $availability->name);
    }

    public function test_create_with_for_auto_resets_context(): void
    {
        // First call with for()
        $availability1 = $this->service->for($this->testCar)->create(AvailabilityRecord::from([
            'name' => 'Availability 1',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        $this->assertEquals(TestCar::class, $availability1->schedulable_type);

        // Second call without for() should work normally
        $availability2 = $this->service->create(AvailabilityRecord::from([
            'name' => 'Availability 2',
            'days' => ['tuesday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]));

        $this->assertInstanceOf(Availability::class, $availability2);
        $this->assertEquals('Availability 2', $availability2->name);
    }

    public function test_find_by_schedulable_without_parameter_uses_scoped_entity(): void
    {
        $this->service->for($this->testCar)->create(AvailabilityRecord::from([
            'name' => 'Test Availability',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        $availabilities = $this->service->for($this->testCar)->findBySchedulable();

        $this->assertCount(1, $availabilities);
        $this->assertEquals('Test Availability', $availabilities->first()->name);
    }

    public function test_find_by_schedulable_with_limit(): void
    {
        // Arrange - Create 5 availabilities with different days to avoid overlap
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        for ($i = 0; $i < 5; $i++) {
            $this->service->for($this->testCar)->create(AvailabilityRecord::from([
                'name' => "Availability $i",
                'days' => [$days[$i]],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));
        }

        // Act
        $availabilities = $this->service->for($this->testCar)->findBySchedulable(null, 3);

        // Assert
        $this->assertCount(3, $availabilities);
        $this->assertEquals('Availability 0', $availabilities->first()->name);
        $this->assertEquals('Availability 2', $availabilities->last()->name);
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

        // Create availability for anotherCar
        $this->service->for($anotherCar)->create(AvailabilityRecord::from([
            'name' => 'Another Availability',
            'days' => ['tuesday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        // Search with explicit entity
        $availabilities = $this->service->for($this->testCar)->findBySchedulable($anotherCar);

        $this->assertCount(1, $availabilities);
        $this->assertEquals('Another Availability', $availabilities->first()->name);
    }

    public function test_find_by_schedulable_without_entity_and_without_for_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No schedulable entity defined');

        $this->service->findBySchedulable();
    }

    public function test_find_with_for_filters_by_ownership(): void
    {
        $availability1 = $this->service->for($this->testCar)->create(AvailabilityRecord::from([
            'name' => 'Test Availability 1',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        // Find with for() - should return own availability
        $found = $this->service->for($this->testCar)->find($availability1->id);
        $this->assertNotNull($found);
        $this->assertEquals($availability1->id, $found->id);

        // Should not find availability with wrong ID
        $notFound = $this->service->for($this->testCar)->find(99999);
        $this->assertNull($notFound);
    }

    public function test_find_with_for_returns_null_for_other_entity_availability(): void
    {
        $anotherCar = ChronosMutationContext::withAllowed(function () {
            return TestCar::create([
                'model' => 'Another Model',
                'license_plate' => 'TEST456',
                'type' => 'suv',
                'capacity' => 7,
            ]);
        });

        $availability2 = $this->service->for($anotherCar)->create(AvailabilityRecord::from([
            'name' => 'Test Availability 2',
            'days' => ['tuesday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        // Find with for() for testCar - should not find anotherCar's availability
        $notFound = $this->service->for($this->testCar)->find($availability2->id);
        $this->assertNull($notFound);
    }

    public function test_delete_with_for_verifies_ownership(): void
    {
        $availability = $this->service->for($this->testCar)->create(AvailabilityRecord::from([
            'name' => 'Test Availability',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
        ]));

        $deleted = $this->service->for($this->testCar)->delete($availability->id);

        $this->assertTrue($deleted);
        $this->assertNotNull(Availability::withTrashed()->find($availability->id)->deleted_at);
    }

    public function test_chained_methods_with_for_work_correctly(): void
    {
        // Create with chained for()
        $availability = $this->service
            ->for($this->testCar)
            ->create(AvailabilityRecord::from([
                'name' => 'Chain Test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));

        $this->assertEquals(TestCar::class, $availability->schedulable_type);
        $this->assertEquals($this->testCar->id, $availability->schedulable_id);

        // Update with chained for() - specifying all fields to avoid validation errors
        $updated = $this->service
            ->for($this->testCar)
            ->update($availability->id, AvailabilityRecord::from([
                'name' => 'Updated Chain Test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '18:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));

        $this->assertEquals('Updated Chain Test', $updated->name);
        $this->assertEquals('18:00:00', $updated->daily_end->format('H:i:s'));

        // Find by schedulable with chained for()
        $availabilities = $this->service
            ->for($this->testCar)
            ->findBySchedulable();

        $this->assertCount(1, $availabilities);
        $this->assertEquals('Updated Chain Test', $availabilities->first()->name);
    }

    // ============================================================
    // TESTS: LIMIT
    // ============================================================

    public function test_find_by_type_with_limit(): void
    {
        // Arrange - Create 5 availabilities with type 'test' and different days
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        for ($i = 0; $i < 5; $i++) {
            $this->service->for($this->testCar)->create(AvailabilityRecord::from([
                'name' => "Availability $i",
                'type' => 'test',
                'days' => [$days[$i]],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));
        }

        // Act
        $availabilities = $this->service->findByType('test', 3);

        // Assert
        $this->assertCount(3, $availabilities);
        $this->assertEquals('Availability 0', $availabilities->first()->name);
    }

    public function test_find_active_at_date_with_limit(): void
    {
        // Arrange - Create 5 availabilities active on date with different days
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        for ($i = 0; $i < 5; $i++) {
            $this->service->for($this->testCar)->create(AvailabilityRecord::from([
                'name' => "Availability $i",
                'days' => [$days[$i]],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));
        }

        // Act
        $availabilities = $this->service->findActiveAtDate($this->testCar, $date, 3);

        // Assert
        $this->assertCount(3, $availabilities);
    }

    public function test_find_active_in_date_range_with_limit(): void
    {
        // Arrange - Create 5 availabilities in date range with different days
        $start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-12-31T23:59:59Z');
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        for ($i = 0; $i < 5; $i++) {
            $this->service->for($this->testCar)->create(AvailabilityRecord::from([
                'name' => "Availability $i",
                'days' => [$days[$i]],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));
        }

        // Act
        $availabilities = $this->service->findActiveInDateRange($this->testCar, $start, $end, 3);

        // Assert
        $this->assertCount(3, $availabilities);
    }

    // ============================================================
    // TESTS: ORIGINAL METHODS
    // ============================================================

    public function test_can_create_availability(): void
    {
        $record = $this->createAvailabilityRecord();

        $availability = $this->service->create($record);

        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Test Availability',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);
    }

    public function test_throws_validation_exception_when_required_fields_missing(): void
    {
        $this->expectException(ValidationException::class);

        $record = new AvailabilityRecord(
            name: 'Test',
        );

        $this->service->create($record);
    }

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
            'schedulable_id' => $this->testCar->id,
        ]);

        $updated = $this->service->update($availability->id, $record);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_throws_exception_when_updating_nonexistent_availability(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $record = $this->createAvailabilityRecord();
        $this->service->update(99999, $record);
    }

    public function test_can_delete_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        $deleted = $this->service->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
    }

    public function test_throws_exception_when_deleting_availability_with_future_bookings(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            DB::table('schedules')->insert([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Future Schedule',
                'start_datetime' => '2025-01-15 10:00:00',
                'end_datetime' => '2025-01-15 11:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->expectException(ValidationException::class);

        $this->service->delete($availability->id);
    }

    public function test_can_force_delete_availability_with_future_bookings(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            DB::table('schedules')->insert([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Future Schedule',
                'start_datetime' => '2025-01-15 10:00:00',
                'end_datetime' => '2025-01-15 11:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $deleted = $this->service->delete($availability->id, true);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $availability->id]);
    }

    public function test_can_find_availability_by_id(): void
    {
        $availability = $this->createAvailability();

        $found = $this->service->find($availability->id);

        $this->assertNotNull($found);
        $this->assertEquals($availability->id, $found->id);
    }

    public function test_find_by_schedulable_returns_availabilities(): void
    {
        $availability1 = $this->createAvailability();

        $record2 = AvailabilityRecord::from([
            'name' => 'Second Availability',
            'type' => 'test',
            'days' => ['tuesday', 'thursday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $this->testCar->id,
        ]);

        $availability2 = $this->service->create($record2);

        $results = $this->service->findBySchedulable($this->testCar);

        $this->assertCount(2, $results);
        $this->assertEquals($availability1->id, $results[0]->id);
        $this->assertEquals($availability2->id, $results[1]->id);
    }

    public function test_find_by_type_returns_availabilities(): void
    {
        $availability = $this->createAvailability();

        $results = $this->service->findByType('test');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_find_active_at_date_returns_availabilities(): void
    {
        $availability = $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-06-15T12:00:00Z');

        $results = $this->service->findActiveAtDate($this->testCar, $date);

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_find_active_in_date_range_returns_availabilities(): void
    {
        $availability = $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-12-31T23:59:59Z');

        $results = $this->service->findActiveInDateRange($this->testCar, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($availability->id, $results[0]->id);
    }

    public function test_schedulable_exists_returns_true_when_exists(): void
    {
        $exists = $this->service->schedulableExists($this->testCar);
        $this->assertTrue($exists);
    }

    public function test_schedulable_exists_returns_false_when_not_exists(): void
    {
        $unknownCar = new TestCar;
        $unknownCar->id = 99999;

        $exists = $this->service->schedulableExists($unknownCar);
        $this->assertFalse($exists);
    }

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
            'schedulable_id' => $this->testCar->id,
        ]);
    }

    private function createAvailability(): Availability
    {
        $record = $this->createAvailabilityRecord();

        return $this->service->create($record);
    }
}
