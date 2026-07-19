<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Services;

use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ImpedimentServiceTest extends IntegrationTestCase
{
    private ImpedimentServiceInterface $impedimentService;

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

        $this->impedimentService = $this->app->make(ImpedimentServiceInterface::class);
        $this->availabilityService = $this->app->make(AvailabilityServiceInterface::class);
    }

    // ============================================================
    // TESTS: FOR() METHOD
    // ============================================================

    public function test_for_method_sets_schedulable_context(): void
    {
        $result = $this->impedimentService->for($this->testCar);

        $this->assertInstanceOf(ImpedimentServiceInterface::class, $result);
    }

    public function test_find_by_schedulable_without_parameter_uses_scoped_entity(): void
    {
        $availability = $this->createAvailability();

        $this->impedimentService->for($this->testCar)->create(ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]));

        $impediments = $this->impedimentService->for($this->testCar)->findBySchedulable();

        $this->assertCount(1, $impediments);
        $this->assertEquals('Test Impediment', $impediments->first()->reason);
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

        $this->impedimentService->for($anotherCar)->create(ImpedimentRecord::from([
            'availability_id' => $anotherAvailability->id,
            'reason' => 'Another Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]));

        $impediments = $this->impedimentService->for($this->testCar)->findBySchedulable($anotherCar);

        $this->assertCount(1, $impediments);
        $this->assertEquals('Another Impediment', $impediments->first()->reason);
    }

    public function test_find_by_schedulable_without_entity_and_without_for_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No schedulable entity defined');

        $this->impedimentService->findBySchedulable();
    }

    public function test_find_with_for_filters_by_ownership(): void
    {
        $availability = $this->createAvailability();

        $impediment = $this->impedimentService->for($this->testCar)->create(ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]));

        $found = $this->impedimentService->for($this->testCar)->find($impediment->id);
        $this->assertNotNull($found);
        $this->assertEquals($impediment->id, $found->id);

        $notFound = $this->impedimentService->for($this->testCar)->find(99999);
        $this->assertNull($notFound);
    }

    public function test_find_with_for_returns_null_for_other_entity_impediment(): void
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

        $impediment2 = $this->impedimentService->for($anotherCar)->create(ImpedimentRecord::from([
            'availability_id' => $anotherAvailability->id,
            'reason' => 'Another Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]));

        $notFound = $this->impedimentService->for($this->testCar)->find($impediment2->id);
        $this->assertNull($notFound);
    }

    public function test_delete_with_for_verifies_ownership(): void
    {
        $availability = $this->createAvailability();

        $impediment = $this->impedimentService->for($this->testCar)->create(ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]));

        $deleted = $this->impedimentService->for($this->testCar)->delete($impediment->id);

        $this->assertTrue($deleted);
        $this->assertNotNull(Impediment::withTrashed()->find($impediment->id)->deleted_at);
    }

    public function test_chained_methods_with_for_work_correctly(): void
    {
        $availability = $this->createAvailability();

        $impediment = $this->impedimentService
            ->for($this->testCar)
            ->create(ImpedimentRecord::from([
                'availability_id' => $availability->id,
                'reason' => 'Chain Test',
                'start_datetime' => '2024-01-15T10:00:00Z',
                'end_datetime' => '2024-01-15T12:00:00Z',
            ]));

        $this->assertEquals($availability->id, $impediment->availability_id);

        $updated = $this->impedimentService
            ->for($this->testCar)
            ->update($impediment->id, ImpedimentRecord::from([
                'reason' => 'Updated Chain Test',
            ]));

        $this->assertEquals('Updated Chain Test', $updated->reason);

        $impediments = $this->impedimentService
            ->for($this->testCar)
            ->findBySchedulable();

        $this->assertCount(1, $impediments);
    }

    // ============================================================
    // TESTS: ORIGINAL METHODS
    // ============================================================

    public function test_can_create_impediment(): void
    {
        $availability = $this->createAvailability();

        $record = $this->createImpedimentRecord($availability);
        $impediment = $this->impedimentService->create($record);

        $this->assertInstanceOf(Impediment::class, $impediment);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Test Impediment',
            'availability_id' => $availability->id,
        ]);
    }

    public function test_throws_validation_exception_when_impediment_overlaps_schedule(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $record = new ImpedimentRecord(
            availability_id: $availability->id,
            reason: 'Overlapping Impediment',
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );

        $this->expectException(ValidationException::class);
        $this->impedimentService->create($record);
    }

    public function test_can_update_impediment(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $record = ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Updated Reason',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]);

        $updated = $this->impedimentService->update($impediment->id, $record);

        $this->assertEquals('Updated Reason', $updated->reason);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Updated Reason',
        ]);
    }

    public function test_throws_exception_when_updating_nonexistent_impediment(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $record = new ImpedimentRecord;
        $this->impedimentService->update(99999, $record);
    }

    public function test_can_delete_impediment(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);
        $id = $impediment->id;

        $deleted = $this->impedimentService->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('impediments', ['id' => $id]);
        $this->assertNotNull(Impediment::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_find_impediment_by_id(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $found = $this->impedimentService->find($impediment->id);

        $this->assertNotNull($found);
        $this->assertEquals($impediment->id, $found->id);
    }

    public function test_find_by_availability_returns_impediments(): void
    {
        $availability = $this->createAvailability();

        $impediment1 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Impediment 1',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $impediment2 = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Impediment 2',
                'start_datetime' => '2024-01-15 11:30:00',
                'end_datetime' => '2024-01-15 12:30:00',
            ]);
        });

        $results = $this->impedimentService->findByAvailability($availability->id);

        $this->assertCount(2, $results);
        $this->assertEquals($impediment1->id, $results[0]->id);
        $this->assertEquals($impediment2->id, $results[1]->id);
    }

    public function test_find_by_availability_with_limit(): void
    {
        $availability = $this->createAvailability();

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Impediment $i",
                    'start_datetime' => '2024-01-15 10:00:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $results = $this->impedimentService->findByAvailability($availability->id, 3);

        $this->assertCount(3, $results);
        $this->assertEquals('Impediment 1', $results->first()->reason);
        $this->assertEquals('Impediment 3', $results->last()->reason);
    }

    public function test_find_by_schedulable_with_limit(): void
    {
        $availability = $this->createAvailability();

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Impediment $i",
                    'start_datetime' => '2024-01-15 10:00:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $results = $this->impedimentService->findBySchedulable($this->testCar, 3);

        $this->assertCount(3, $results);
    }

    public function test_find_by_date_with_limit(): void
    {
        $availability = $this->createAvailability();
        $date = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Impediment $i",
                    'start_datetime' => '2024-01-15 10:00:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $results = $this->impedimentService->findByDate($date, null, 3);

        $this->assertCount(3, $results);
    }

    public function test_find_in_date_range_with_limit(): void
    {
        $availability = $this->createAvailability();
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Impediment $i",
                    'start_datetime' => '2024-01-15 10:00:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $results = $this->impedimentService->findInDateRange($start, $end, null, 3);

        $this->assertCount(3, $results);
    }

    public function test_find_active_with_limit(): void
    {
        $availability = $this->createAvailability();

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Active Impediment $i",
                    'start_datetime' => now()->subHour(),
                    'end_datetime' => now()->addHour(),
                ]);
            });
        }

        $results = $this->impedimentService->findActive(null, 3);

        $this->assertCount(3, $results);
        $this->assertEquals('Active Impediment 1', $results->first()->reason);
        $this->assertEquals('Active Impediment 3', $results->last()->reason);
    }

    public function test_search_by_reason_with_limit(): void
    {
        $availability = $this->createAvailability();

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Impediment::create([
                    'availability_id' => $availability->id,
                    'reason' => "Test Impediment $i",
                    'start_datetime' => '2024-01-15 10:00:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $results = $this->impedimentService->searchByReason('Test', null, 3);

        $this->assertCount(3, $results);
    }

    public function test_get_blocked_schedules_with_limit(): void
    {
        $availability = $this->createAvailability();

        $impediment = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Test Impediment',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Schedule::create([
                    'availability_id' => $availability->id,
                    'schedulable_type' => TestCar::class,
                    'schedulable_id' => $this->testCar->id,
                    'title' => "Blocked Schedule $i",
                    'start_datetime' => '2024-01-15 10:30:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $blocked = $this->impedimentService->getBlockedSchedules($impediment, 3);

        $this->assertCount(3, $blocked);
        $this->assertEquals('Blocked Schedule 1', $blocked->first()->title);
        $this->assertEquals('Blocked Schedule 3', $blocked->last()->title);
    }

    public function test_get_fully_blocked_schedules_with_limit(): void
    {
        $availability = $this->createAvailability();

        $impediment = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Test Impediment',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        for ($i = 1; $i <= 5; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Schedule::create([
                    'availability_id' => $availability->id,
                    'schedulable_type' => TestCar::class,
                    'schedulable_id' => $this->testCar->id,
                    'title' => "Fully Blocked $i",
                    'start_datetime' => '2024-01-15 10:30:00',
                    'end_datetime' => '2024-01-15 11:00:00',
                ]);
            });
        }

        $fullyBlocked = $this->impedimentService->getFullyBlockedSchedules($impediment, 3);

        $this->assertCount(3, $fullyBlocked);
        $this->assertEquals('Fully Blocked 1', $fullyBlocked->first()->title);
        $this->assertEquals('Fully Blocked 3', $fullyBlocked->last()->title);
    }

    public function test_get_partially_blocked_schedules_with_limit(): void
    {
        $availability = $this->createAvailability();

        $impediment = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Test Impediment',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        for ($i = 1; $i <= 3; $i++) {
            ChronosMutationContext::withAllowed(function () use ($availability, $i) {
                Schedule::create([
                    'availability_id' => $availability->id,
                    'schedulable_type' => TestCar::class,
                    'schedulable_id' => $this->testCar->id,
                    'title' => "Partially Blocked $i",
                    'start_datetime' => '2024-01-15 09:30:00',
                    'end_datetime' => '2024-01-15 10:30:00',
                ]);
            });
        }

        $partiallyBlocked = $this->impedimentService->getPartiallyBlockedSchedules($impediment, 2);

        $this->assertCount(2, $partiallyBlocked);
    }

    public function test_find_by_schedulable_returns_impediments(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $results = $this->impedimentService->findBySchedulable($this->testCar);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_find_active_returns_active_impediments(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Active Impediment',
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]);

            Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Past Impediment',
                'start_datetime' => now()->subDays(2),
                'end_datetime' => now()->subDays(1),
            ]);
        });

        $results = $this->impedimentService->findActive();

        $this->assertCount(1, $results);
        $this->assertEquals('Active Impediment', $results[0]->reason);
    }

    public function test_search_by_reason_returns_matching_impediments(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $results = $this->impedimentService->searchByReason('Test Impediment');

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_is_active_returns_true_for_active_impediment(): void
    {
        $availability = $this->createAvailability();

        $impediment = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Active Impediment',
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]);
        });

        $this->assertTrue($this->impedimentService->isActive($impediment));
    }

    public function test_overlaps_with_returns_true_when_overlapping(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');

        $this->assertTrue($this->impedimentService->overlapsWith($impediment, $start, $end));
    }

    public function test_get_blocked_schedules_returns_blocked_schedules(): void
    {
        $availability = $this->createAvailability();

        $impediment = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Impediment::create([
                'availability_id' => $availability->id,
                'reason' => 'Test Impediment',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 12:00:00',
            ]);
        });

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Blocked Schedule',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $blocked = $this->impedimentService->getBlockedSchedules($impediment);

        $this->assertCount(1, $blocked);
        $this->assertEquals('Blocked Schedule', $blocked[0]->title);
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
            'days' => ['monday', 'wednesday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => $car->id,
        ]);

        return $this->availabilityService->create($record);
    }

    private function createImpedimentRecord(Availability $availability): ImpedimentRecord
    {
        return ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]);
    }

    private function createImpediment(Availability $availability): Impediment
    {
        $record = $this->createImpedimentRecord($availability);

        return $this->impedimentService->create($record);
    }
}
