<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Repositories;

use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ImpedimentRepositoryTest extends IntegrationTestCase
{
    private ImpedimentRepository $repository;

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

        $this->repository = $this->app->make(ImpedimentRepository::class);
        $this->availabilityRepository = $this->app->make(AvailabilityRepository::class);

        // Désactiver l'enforcement du service layer pour les tests
        $this->repository->withoutServiceEnforcement();
        $this->availabilityRepository->withoutServiceEnforcement();
    }

    // ============================================================
    // CREATE TESTS
    // ============================================================

    public function test_can_create_impediment(): void
    {
        $availability = $this->createAvailability();

        $record = $this->createImpedimentRecord($availability);
        $impediment = $this->repository->create($record);

        $this->assertInstanceOf(Impediment::class, $impediment);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Test Impediment',
            'availability_id' => $availability->id,
        ]);
    }

    public function test_can_create_raw_impediment(): void
    {
        $availability = $this->createAvailability();

        $data = [
            'availability_id' => $availability->id,
            'reason' => 'Raw Impediment',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:00:00',
        ];

        $impediment = $this->repository->createRaw($data);

        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Raw Impediment',
        ]);
    }

    // ============================================================
    // READ TESTS
    // ============================================================

    public function test_can_find_impediment_by_id(): void
    {
        $impediment = $this->createImpediment();
        $found = $this->repository->find($impediment->id);

        $this->assertNotNull($found);
        $this->assertEquals($impediment->id, $found->id);
    }

    public function test_can_find_by_availability(): void
    {
        $availability = $this->createAvailability();
        $impediment1 = $this->createImpediment($availability);
        $impediment2 = $this->createImpediment($availability);

        $results = $this->repository->findByAvailability($availability->id);

        $this->assertCount(2, $results);
        $this->assertEquals($impediment1->id, $results[0]->id);
    }

    public function test_can_search_by_reason(): void
    {
        $impediment = $this->createImpediment();

        $results = $this->repository->searchByReason('Test Impediment');

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_search_by_reason_with_availability_filter(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $impediment1 = $this->createImpediment($availability1);
        $this->createImpediment($availability2);

        $results = $this->repository->searchByReason('Test Impediment', $availability1->id);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment1->id, $results[0]->id);
    }

    public function test_can_find_by_date(): void
    {
        $impediment = $this->createImpediment();
        $date = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        $results = $this->repository->findByDate($date);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_find_in_date_range(): void
    {
        $impediment = $this->createImpediment();
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');

        $results = $this->repository->findInDateRange($start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_find_by_availability_in_date_range(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);
        $start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');

        $results = $this->repository->findByAvailabilityInDateRange($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_find_active(): void
    {
        $availability = $this->createAvailability();

        $now = Carbon::now('UTC');

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Active',
            'start_datetime' => $now->copy()->subHour()->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->addHour()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Expired',
            'start_datetime' => $now->copy()->subDays(2)->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->subDays(1)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Future',
            'start_datetime' => $now->copy()->addDays(1)->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->addDays(2)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findActive();

        $this->assertCount(1, $results);
        $this->assertEquals('Active', $results[0]->reason);
    }

    public function test_can_find_active_with_availability_filter(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();

        $now = Carbon::now('UTC');

        DB::table('impediments')->insert([
            'availability_id' => $availability1->id,
            'reason' => 'Active 1',
            'start_datetime' => $now->copy()->subHour()->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->addHour()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('impediments')->insert([
            'availability_id' => $availability2->id,
            'reason' => 'Active 2',
            'start_datetime' => $now->copy()->subHour()->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->addHour()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('impediments')->insert([
            'availability_id' => $availability1->id,
            'reason' => 'Expired',
            'start_datetime' => $now->copy()->subDays(2)->format('Y-m-d H:i:s'),
            'end_datetime' => $now->copy()->subDays(1)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findActive($availability1->id);

        $this->assertCount(1, $results);
        $this->assertEquals('Active 1', $results[0]->reason);
    }

    public function test_can_find_overlapping(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');

        $results = $this->repository->findOverlapping($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_find_overlapping_excludes_given_id(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');

        $results = $this->repository->findOverlapping($availability->id, $start, $end, $impediment->id);

        $this->assertCount(0, $results);
    }

    public function test_can_find_conflicting(): void
    {
        $availability = $this->createAvailability();
        $impediment = $this->createImpediment($availability);

        $start = DateTimeZuluVO::from('2024-01-15T09:30:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T11:30:00Z');

        $results = $this->repository->findConflicting($availability->id, $start, $end);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_find_by_schedulable(): void
    {
        // Create availability for TestCar
        $availability = $this->createAvailability();

        // Create impediment linked to this availability
        $impediment = $this->createImpediment($availability);

        // Find impediments by schedulable (TestCar)
        $results = $this->repository->findBySchedulable($this->testCar);

        $this->assertCount(1, $results);
        $this->assertEquals($impediment->id, $results[0]->id);
    }

    public function test_can_find_with_invalid_chronology(): void
    {
        $availability = $this->createAvailability();

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Invalid Chronology',
            'start_datetime' => '2024-01-15 11:00:00',
            'end_datetime' => '2024-01-15 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findWithInvalidChronology();

        $this->assertCount(1, $results);
        $this->assertEquals('Invalid Chronology', $results[0]->reason);
    }

    public function test_can_find_with_exceeding_duration(): void
    {
        $availability = $this->createAvailability();

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Long Impediment',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('impediments')->insert([
            'availability_id' => $availability->id,
            'reason' => 'Short Impediment',
            'start_datetime' => '2024-01-15 13:00:00',
            'end_datetime' => '2024-01-15 13:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->repository->findWithExceedingDuration($availability->id, 60);

        $this->assertCount(1, $results);
        $this->assertEquals('Long Impediment', $results[0]->reason);
    }

    // ============================================================
    // UPDATE TESTS
    // ============================================================

    public function test_can_update_impediment(): void
    {
        $impediment = $this->createImpediment();

        $record = ImpedimentRecord::from([
            'availability_id' => $impediment->availability_id,
            'reason' => 'Updated Reason',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]);

        $updated = $this->repository->update($impediment->id, $record);

        $this->assertEquals('Updated Reason', $updated->reason);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Updated Reason',
        ]);
    }

    public function test_can_update_raw_impediment(): void
    {
        $impediment = $this->createImpediment();

        $data = ['reason' => 'Raw Updated'];

        $updated = $this->repository->updateRaw($impediment->id, $data);

        $this->assertEquals('Raw Updated', $updated->reason);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Raw Updated',
        ]);
    }

    // ============================================================
    // DELETE TESTS
    // ============================================================

    public function test_can_delete_impediment(): void
    {
        $impediment = $this->createImpediment();
        $id = $impediment->id;

        $deleted = $this->repository->delete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseHas('impediments', ['id' => $id]);
        $this->assertNotNull(Impediment::withTrashed()->find($id)->deleted_at);
    }

    public function test_can_restore_impediment(): void
    {
        $impediment = $this->createImpediment();
        $id = $impediment->id;
        $this->repository->delete($id);

        $restored = $this->repository->restore($id);

        $this->assertTrue($restored);
        $this->assertDatabaseHas('impediments', ['id' => $id]);
        $this->assertNull(Impediment::find($id)->deleted_at);
    }

    public function test_can_force_delete_impediment(): void
    {
        $impediment = $this->createImpediment();
        $id = $impediment->id;

        $deleted = $this->repository->forceDelete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('impediments', ['id' => $id]);
    }

    public function test_can_bulk_delete_impediments(): void
    {
        $availability = $this->createAvailability();
        $impediment1 = $this->createImpediment($availability);
        $impediment2 = $this->createImpediment($availability);

        $criteria = ImpedimentRecord::from([
            'availability_id' => $availability->id,
        ]);

        $deleted = $this->repository->deleteBulk($criteria);

        $this->assertEquals(2, $deleted);
        $this->assertNotNull(Impediment::withTrashed()->find($impediment1->id)->deleted_at);
        $this->assertNotNull(Impediment::withTrashed()->find($impediment2->id)->deleted_at);
    }

    // ============================================================
    // COUNT TESTS
    // ============================================================

    public function test_can_count_impediments(): void
    {
        $this->createImpediment();
        $this->createImpediment();

        $count = $this->repository->count();
        $this->assertEquals(2, $count);
    }

    // ============================================================
    // NEW TESTS FOR BLOCKED SCHEDULES
    // ============================================================

    public function test_get_blocked_schedules_returns_schedules_that_overlap(): void
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

        // Create schedule that overlaps with impediment
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Overlapping Schedule',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        // Create schedule that does not overlap
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Non-overlapping Schedule',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 09:30:00',
            ]);
        });

        $blocked = $this->repository->getBlockedSchedules($impediment);

        $this->assertCount(1, $blocked);
        $this->assertEquals('Overlapping Schedule', $blocked[0]->title);
    }

    public function test_get_fully_blocked_schedules_returns_completely_contained_schedules(): void
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

        // Create schedule fully inside impediment
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Fully Blocked',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        // Create schedule partially overlapping
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Partially Blocked',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:30:00',
            ]);
        });

        $fullyBlocked = $this->repository->getFullyBlockedSchedules($impediment);

        $this->assertCount(1, $fullyBlocked);
        $this->assertEquals('Fully Blocked', $fullyBlocked[0]->title);
    }

    public function test_get_partially_blocked_schedules_returns_partially_overlapping_schedules(): void
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

        // Create schedule starting before, ending inside
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Starts Before',
                'start_datetime' => '2024-01-15 09:30:00',
                'end_datetime' => '2024-01-15 10:30:00',
            ]);
        });

        // Create schedule starting inside, ending after
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Ends After',
                'start_datetime' => '2024-01-15 11:30:00',
                'end_datetime' => '2024-01-15 12:30:00',
            ]);
        });

        // Create schedule fully inside (should not be included)
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Fully Inside',
                'start_datetime' => '2024-01-15 10:30:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $partiallyBlocked = $this->repository->getPartiallyBlockedSchedules($impediment);

        $this->assertCount(2, $partiallyBlocked);
        $titles = $partiallyBlocked->pluck('title')->toArray();
        $this->assertContains('Starts Before', $titles);
        $this->assertContains('Ends After', $titles);
    }

    public function test_get_blocked_schedules_returns_empty_collection_when_no_overlap(): void
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

        // Create schedule before impediment
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'Before Impediment',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 09:30:00',
            ]);
        });

        // Create schedule after impediment
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => $this->testCar->id,
                'title' => 'After Impediment',
                'start_datetime' => '2024-01-15 12:30:00',
                'end_datetime' => '2024-01-15 13:00:00',
            ]);
        });

        $blocked = $this->repository->getBlockedSchedules($impediment);

        $this->assertCount(0, $blocked);
    }

    public function test_get_blocked_schedules_returns_empty_when_no_schedules(): void
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

        // Aucun schedule créé
        $blocked = $this->repository->getBlockedSchedules($impediment);
        $fullyBlocked = $this->repository->getFullyBlockedSchedules($impediment);
        $partiallyBlocked = $this->repository->getPartiallyBlockedSchedules($impediment);

        $this->assertCount(0, $blocked);
        $this->assertCount(0, $fullyBlocked);
        $this->assertCount(0, $partiallyBlocked);
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

    private function createImpedimentRecord(Availability $availability): ImpedimentRecord
    {
        return ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]);
    }

    private function createImpediment(?Availability $availability = null): Impediment
    {
        if ($availability === null) {
            $availability = $this->createAvailability();
        }

        $record = $this->createImpedimentRecord($availability);

        return $this->repository->create($record);
    }
}
