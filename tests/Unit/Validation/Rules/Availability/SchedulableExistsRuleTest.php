<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\SchedulableExistsRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class SchedulableExistsRuleTest extends IntegrationTestCase
{
    private SchedulableExistsRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new SchedulableExistsRule;
    }

    public function test_supports_create_and_update_operations(): void
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

        $this->assertTrue($this->rule->supports($createContext));
        $this->assertTrue($this->rule->supports($updateContext));
        $this->assertFalse($this->rule->supports($deleteContext));
    }

    public function test_returns_null_for_impediment_record(): void
    {
        $record = new ImpedimentRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_null_when_schedulable_data_is_missing(): void
    {
        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_class_does_not_exist(): void
    {
        $record = new AvailabilityRecord(
            schedulable_type: 'Invalid\\Class\\That\\Does\\Not\\Exist',
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Schedulable class "Invalid\\Class\\That\\Does\\Not\\Exist" does not exist', $result->message);
    }

    public function test_returns_error_when_entity_does_not_exist(): void
    {
        $record = new AvailabilityRecord(
            schedulable_type: TestCar::class,
            schedulable_id: 99999,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Schedulable entity #99999 of type "'.TestCar::class.'" does not exist', $result->message);
    }

    public function test_passes_when_entity_exists(): void
    {
        // Create a TestCar
        TestCar::create([
            'model' => 'Test Model',
            'license_plate' => 'TEST123',
        ]);

        $record = new AvailabilityRecord(
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_for_schedule_record_when_entity_exists(): void
    {
        // Create a TestCar
        TestCar::create([
            'model' => 'Test Model',
            'license_plate' => 'TEST123',
        ]);

        $record = new ScheduleRecord(
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
