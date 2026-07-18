<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration;

use AndyDefer\LaravelChronos\Exceptions\ForbiddenModelMutationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Observers\EnforceDomainMutationObserver;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

final class MutationContextTest extends IntegrationTestCase
{
    private AvailabilityRepository $availabilityRepository;

    private ScheduleRepository $scheduleRepository;

    private ImpedimentRepository $impedimentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Désactiver l'enforcement du service layer pour les tests
        $this->availabilityRepository = $this->app->make(AvailabilityRepository::class);
        $this->availabilityRepository->withoutServiceEnforcement();

        $this->scheduleRepository = $this->app->make(ScheduleRepository::class);
        $this->scheduleRepository->withoutServiceEnforcement();

        $this->impedimentRepository = $this->app->make(ImpedimentRepository::class);
        $this->impedimentRepository->withoutServiceEnforcement();

        // Ensure SoftDeletes trait is used
        $this->assertTrue($this->usesSoftDeletes(Availability::class));
        $this->assertTrue($this->usesSoftDeletes(Schedule::class));
        $this->assertTrue($this->usesSoftDeletes(Impediment::class));
    }

    /**
     * Check if a model uses SoftDeletes trait.
     */
    private function usesSoftDeletes(string $modelClass): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($modelClass)
        );
    }

    // ============================================================
    // TESTS: Repository operations (should work)
    // ============================================================

    public function test_repository_can_create_availability(): void
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

        $availability = $this->availabilityRepository->create($record);

        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Test Availability',
            'schedulable_type' => TestCar::class,
        ]);
    }

    public function test_repository_can_create_schedule(): void
    {
        $availability = $this->createAvailability();

        $record = ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
            'title' => 'Test Schedule',
            'description' => 'Test Description',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        $schedule = $this->scheduleRepository->create($record);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Test Schedule',
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
        ]);
    }

    public function test_repository_can_create_impediment(): void
    {
        $availability = $this->createAvailability();

        $record = ImpedimentRecord::from([
            'availability_id' => $availability->id,
            'reason' => 'Test Impediment',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T12:00:00Z',
        ]);

        $impediment = $this->impedimentRepository->create($record);

        $this->assertInstanceOf(Impediment::class, $impediment);
        $this->assertDatabaseHas('impediments', [
            'id' => $impediment->id,
            'reason' => 'Test Impediment',
            'availability_id' => $availability->id,
        ]);
    }

    public function test_repository_can_update_availability(): void
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

        $updated = $this->availabilityRepository->update($availability->id, $record);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertDatabaseHas('availabilities', [
            'id' => $availability->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_repository_can_update_schedule(): void
    {
        $availability = $this->createAvailability();
        $schedule = $this->createSchedule($availability);

        $record = ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
            'title' => 'Updated Schedule Title',
            'description' => 'Updated Description',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        $updated = $this->scheduleRepository->update($schedule->id, $record);

        $this->assertEquals('Updated Schedule Title', $updated->title);
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Updated Schedule Title',
        ]);
    }

    public function test_repository_can_delete_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        $deleted = $this->availabilityRepository->delete($id);

        $this->assertTrue($deleted);

        // With soft deletes, the record should still exist but with deleted_at set
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
    }

    public function test_repository_can_restore_soft_deleted_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;
        $this->availabilityRepository->delete($id);

        $restored = $this->availabilityRepository->restore($id);

        $this->assertTrue($restored);
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNull(Availability::find($id)->deleted_at);
    }

    public function test_repository_can_force_delete_availability(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        $deleted = $this->availabilityRepository->forceDelete($id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('availabilities', ['id' => $id]);
    }

    // ============================================================
    // TESTS: Direct model mutations (should throw exceptions)
    // ============================================================

    public function test_direct_model_create_throws_exception(): void
    {
        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct create operation on Availability is forbidden');

        Availability::create([
            'name' => 'Direct Create',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01 00:00:00',
            'validity_end' => '2024-12-31 23:59:59',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);
    }

    public function test_direct_model_save_throws_exception(): void
    {
        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct create operation on Availability is forbidden');

        $availability = new Availability;
        $availability->name = 'Direct Save';
        $availability->type = 'test';
        $availability->days = ['monday'];
        $availability->daily_start = '09:00:00';
        $availability->daily_end = '17:00:00';
        $availability->validity_start = '2024-01-01 00:00:00';
        $availability->validity_end = '2024-12-31 23:59:59';
        $availability->schedulable_type = TestCar::class;
        $availability->schedulable_id = 1;
        $availability->save();
    }

    public function test_direct_model_update_throws_exception(): void
    {
        $availability = $this->createAvailability();

        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct update operation on Availability is forbidden');

        $found = Availability::find($availability->id);
        $found->name = 'Direct Update';
        $found->save();
    }

    public function test_direct_model_delete_throws_exception(): void
    {
        $availability = $this->createAvailability();

        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct delete operation on Availability is forbidden');

        Availability::destroy($availability->id);
    }

    public function test_direct_model_update_using_query_builder_throws_exception(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        // Query builder updates don't trigger model events, so no exception
        $updated = Availability::where('id', $id)->update(['name' => 'Direct Update']);

        $this->assertEquals(1, $updated);
        $this->assertDatabaseHas('availabilities', [
            'id' => $id,
            'name' => 'Direct Update',
        ]);
    }

    public function test_direct_model_delete_using_query_builder_throws_exception(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        // Query builder deletes don't trigger model events, so no exception
        $deleted = Availability::where('id', $id)->delete();

        $this->assertEquals(1, $deleted);
        // With soft deletes, record still exists with deleted_at
        $this->assertDatabaseHas('availabilities', ['id' => $id]);
        $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
    }

    public function test_direct_schedule_create_throws_exception(): void
    {
        $availability = $this->createAvailability();

        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct create operation on Schedule is forbidden');

        Schedule::create([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
            'title' => 'Direct Schedule',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 11:00:00',
        ]);
    }

    public function test_direct_impediment_create_throws_exception(): void
    {
        $availability = $this->createAvailability();

        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct create operation on Impediment is forbidden');

        Impediment::create([
            'availability_id' => $availability->id,
            'reason' => 'Direct Impediment',
            'start_datetime' => '2024-01-15 10:00:00',
            'end_datetime' => '2024-01-15 12:00:00',
        ]);
    }

    // ============================================================
    // TESTS: Manual context usage
    // ============================================================

    public function test_manual_context_allows_mutations(): void
    {
        ChronosMutationContext::allow();

        try {
            $availability = Availability::create([
                'name' => 'Manual Context',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            $this->assertDatabaseHas('availabilities', ['id' => $availability->id]);
        } finally {
            ChronosMutationContext::disallow();
        }
    }

    public function test_manual_context_allows_updates(): void
    {
        $availability = $this->createAvailability();

        ChronosMutationContext::allow();

        try {
            $found = Availability::find($availability->id);
            $found->name = 'Manual Update';
            $found->save();

            $this->assertDatabaseHas('availabilities', [
                'id' => $availability->id,
                'name' => 'Manual Update',
            ]);
        } finally {
            ChronosMutationContext::disallow();
        }
    }

    public function test_manual_context_allows_deletes(): void
    {
        $availability = $this->createAvailability();
        $id = $availability->id;

        ChronosMutationContext::allow();

        try {
            Availability::destroy($id);

            // With soft deletes, record still exists with deleted_at
            $this->assertDatabaseHas('availabilities', ['id' => $id]);
            $this->assertNotNull(Availability::withTrashed()->find($id)->deleted_at);
        } finally {
            ChronosMutationContext::disallow();
        }
    }

    public function test_with_allowed_helper_works(): void
    {
        $availabilityId = ChronosMutationContext::withAllowed(function () {
            $availability = Availability::create([
                'name' => 'With Allowed Helper',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            return $availability->id;
        });

        $this->assertDatabaseHas('availabilities', ['id' => $availabilityId]);

        // Context should be automatically disallowed
        $this->assertFalse(ChronosMutationContext::isAllowed());
    }

    public function test_with_allowed_passes_context_data(): void
    {
        ChronosMutationContext::withAllowed(
            function () {
                $contextData = ChronosMutationContext::getContextData();

                $this->assertArrayHasKey('test_key', $contextData);
                $this->assertEquals('test_value', $contextData['test_key']);

                Availability::create([
                    'name' => 'With Context Data',
                    'type' => 'test',
                    'days' => ['monday'],
                    'daily_start' => '09:00:00',
                    'daily_end' => '17:00:00',
                    'validity_start' => '2024-01-01 00:00:00',
                    'validity_end' => '2024-12-31 23:59:59',
                    'schedulable_type' => TestCar::class,
                    'schedulable_id' => 1,
                ]);
            },
            ['test_key' => 'test_value']
        );

        // Context data should be cleared after
        $this->assertEmpty(ChronosMutationContext::getContextData());
    }

    // ============================================================
    // TESTS: Observer behavior
    // ============================================================

    public function test_observer_is_registered_for_availability(): void
    {
        $this->assertHasObserver(
            Availability::class,
            EnforceDomainMutationObserver::class
        );
    }

    public function test_observer_is_registered_for_schedule(): void
    {
        $this->assertHasObserver(
            Schedule::class,
            EnforceDomainMutationObserver::class
        );
    }

    public function test_observer_is_registered_for_impediment(): void
    {
        $this->assertHasObserver(
            Impediment::class,
            EnforceDomainMutationObserver::class
        );
    }

    public function test_observer_registered_for_correct_events(): void
    {
        // Availability events
        $this->assertObserverForEvent(
            Availability::class,
            EnforceDomainMutationObserver::class,
            'creating'
        );
        $this->assertObserverForEvent(
            Availability::class,
            EnforceDomainMutationObserver::class,
            'updating'
        );
        $this->assertObserverForEvent(
            Availability::class,
            EnforceDomainMutationObserver::class,
            'deleting'
        );
        $this->assertObserverForEvent(
            Availability::class,
            EnforceDomainMutationObserver::class,
            'restoring'
        );
    }

    public function test_context_id_is_generated_when_allowed(): void
    {
        ChronosMutationContext::allow();

        try {
            $contextId = ChronosMutationContext::getContextId();

            $this->assertNotNull($contextId);
            $this->assertStringStartsWith('chronos_', $contextId);
        } finally {
            ChronosMutationContext::disallow();
        }
    }

    public function test_context_is_disallowed_after_repository_operation(): void
    {
        $this->assertFalse(ChronosMutationContext::isAllowed());

        $record = AvailabilityRecord::from([
            'name' => 'Test',
            'type' => 'test',
            'days' => ['monday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $this->availabilityRepository->create($record);

        // Context should be automatically disallowed after operation
        $this->assertFalse(ChronosMutationContext::isAllowed());
    }

    // ============================================================
    // TESTS: Bulk operations
    // ============================================================

    public function test_repository_can_bulk_delete(): void
    {
        $availability1 = $this->createAvailability();
        $availability2 = $this->createAvailability();
        $id1 = $availability1->id;
        $id2 = $availability2->id;

        $criteria = AvailabilityRecord::from([
            'type' => 'test',
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
        ]);

        $deleted = $this->availabilityRepository->deleteBulk($criteria);

        $this->assertEquals(2, $deleted);

        // With soft deletes, records still exist with deleted_at
        $this->assertDatabaseHas('availabilities', ['id' => $id1]);
        $this->assertDatabaseHas('availabilities', ['id' => $id2]);
        $this->assertNotNull(Availability::withTrashed()->find($id1)->deleted_at);
        $this->assertNotNull(Availability::withTrashed()->find($id2)->deleted_at);
    }

    public function test_direct_bulk_delete_throws_exception(): void
    {
        $availability = $this->createAvailability();

        // Query builder delete doesn't trigger events
        $deleted = Availability::where('type', 'test')->delete();
        $this->assertEquals(1, $deleted);
    }

    // ============================================================
    // TESTS: Transaction behavior
    // ============================================================

    public function test_repository_operations_work_in_transaction(): void
    {
        DB::beginTransaction();

        try {
            $availability = $this->createAvailability();

            $record = ScheduleRecord::from([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Transaction Schedule',
                'start_datetime' => '2024-01-15T10:00:00Z',
                'end_datetime' => '2024-01-15T11:00:00Z',
            ]);

            $schedule = $this->scheduleRepository->create($record);

            $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);

            DB::rollBack();

            $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function test_direct_mutations_fail_even_in_transaction(): void
    {
        $this->expectException(ForbiddenModelMutationException::class);
        $this->expectExceptionMessage('Direct create operation on Availability is forbidden');

        DB::beginTransaction();

        try {
            Availability::create([
                'name' => 'Should Fail',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ============================================================
    // HELPERS
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

        return $this->availabilityRepository->create($record);
    }

    private function createSchedule(Availability $availability): Schedule
    {
        $record = ScheduleRecord::from([
            'availability_id' => $availability->id,
            'schedulable_type' => TestCar::class,
            'schedulable_id' => 1,
            'title' => 'Test Schedule',
            'description' => 'Test Description',
            'start_datetime' => '2024-01-15T10:00:00Z',
            'end_datetime' => '2024-01-15T11:00:00Z',
        ]);

        return $this->scheduleRepository->create($record);
    }
}
