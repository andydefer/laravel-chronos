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
use AndyDefer\LaravelChronos\Validation\Rules\Shared\AvailabilityOwnershipValidationRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class AvailabilityOwnershipValidationRuleTest extends IntegrationTestCase
{
    private AvailabilityOwnershipValidationRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AvailabilityOwnershipValidationRule;
    }

    public function test_supports_schedule_and_impediment_create_operations(): void
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
        $this->assertFalse($this->rule->supports($updateContext));
    }

    public function test_returns_null_when_availability_id_is_missing(): void
    {
        $record = new ScheduleRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_availability_does_not_exist(): void
    {
        $record = new ScheduleRecord(
            availability_id: 99999,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability #99999 does not exist', $result->message);
    }

    public function test_returns_error_when_availability_belongs_to_different_entity(): void
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

        // Créer un schedule pour un entity différent (schedulable_id = 2)
        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 2,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('does not belong to this schedulable entity', $result->message);
    }

    public function test_passes_when_availability_belongs_to_correct_entity(): void
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

        // Créer un schedule pour le même entity (schedulable_id = 1)
        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
