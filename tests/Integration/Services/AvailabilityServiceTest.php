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
