<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\TimeSlotWithinAvailabilityRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class TimeSlotWithinAvailabilityRuleTest extends IntegrationTestCase
{
    private TimeSlotWithinAvailabilityRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $helper = new ValidationHelperService;
        $this->rule = new TimeSlotWithinAvailabilityRule($helper);
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

    public function test_returns_error_when_outside_validity_period(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-01-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-02-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-02-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('outside the validity period', $result->message);
    }

    public function test_returns_error_when_day_not_allowed(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday', 'tuesday', 'wednesday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-06-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-06-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Day "saturday" is not allowed', $result->message);
    }

    public function test_passes_when_within_availability_constraints(): void
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

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-06-10T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-06-10T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
