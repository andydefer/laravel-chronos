<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\NoFutureBookingsOnDeleteRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class NoFutureBookingsOnDeleteRuleTest extends IntegrationTestCase
{
    private NoFutureBookingsOnDeleteRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NoFutureBookingsOnDeleteRule;
    }

    public function test_supports_only_availability_delete_operations(): void
    {
        $createContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::CREATE
        );

        $updateContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::UPDATE
        );

        $deleteContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::DELETE
        );

        $this->assertFalse($this->rule->supports($createContext));
        $this->assertFalse($this->rule->supports($updateContext));
        $this->assertTrue($this->rule->supports($deleteContext));
    }

    public function test_returns_error_when_availability_has_future_schedules(): void
    {
        // Créer une availability avec le contexte ouvert
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        // Créer un schedule futur avec le contexte ouvert
        ChronosMutationContext::withAllowed(function () use ($availability) {
            DB::table('schedules')->insert([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Future Schedule',
                'start_datetime' => Carbon::now('UTC')->addDays(1)->format('Y-m-d H:i:s'),
                'end_datetime' => Carbon::now('UTC')->addDays(1)->addHours(1)->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::DELETE, $availability);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(NoFutureBookingsOnDeleteRule::class, $result->rule);
        $this->assertStringContainsString('Cannot delete availability that has future bookings', $result->message);
    }

    public function test_passes_when_availability_has_no_future_schedules(): void
    {
        // Créer une availability avec le contexte ouvert
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        // Créer un schedule passé avec le contexte ouvert
        ChronosMutationContext::withAllowed(function () use ($availability) {
            DB::table('schedules')->insert([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Past Schedule',
                'start_datetime' => Carbon::now('UTC')->subDays(2)->format('Y-m-d H:i:s'),
                'end_datetime' => Carbon::now('UTC')->subDays(2)->addHours(1)->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::DELETE, $availability);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_when_availability_has_no_schedules(): void
    {
        // Créer une availability avec le contexte ouvert
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::DELETE, $availability);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
