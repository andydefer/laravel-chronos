<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\NoTemporalConflictRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class NoTemporalConflictRuleTest extends IntegrationTestCase
{
    private NoTemporalConflictRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NoTemporalConflictRule;
    }

    public function test_supports_schedule_and_impediment_operations(): void
    {
        $createContext = new ValidationContext(
            new ScheduleRecord,
            OperationType::CREATE
        );

        $updateContext = new ValidationContext(
            new ScheduleRecord,
            OperationType::UPDATE
        );

        $this->assertTrue($this->rule->supports($createContext));
        $this->assertTrue($this->rule->supports($updateContext));
    }

    public function test_returns_null_when_availability_id_is_missing(): void
    {
        $record = new ScheduleRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_schedule_conflicts_with_existing_schedule(): void
    {
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

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('conflicts with existing schedule', $result->message);
    }

    public function test_passes_when_no_conflict_with_schedules(): void
    {
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

        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T12:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_excludes_current_entity_on_update(): void
    {
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

        $existing = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::UPDATE, $existing);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
