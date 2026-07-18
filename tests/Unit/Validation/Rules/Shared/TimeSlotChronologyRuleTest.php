<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\TimeSlotChronologyRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class TimeSlotChronologyRuleTest extends IntegrationTestCase
{
    private TimeSlotChronologyRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new TimeSlotChronologyRule;
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

    public function test_returns_null_when_datetimes_are_missing(): void
    {
        $record = new ScheduleRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_start_after_end(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(TimeSlotChronologyRule::class, $result->rule);
        $this->assertStringContainsString('Start datetime must be before end datetime', $result->message);
    }

    public function test_returns_error_when_start_equal_to_end(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        // La règle vérifie d'abord start < end, donc le message est "Start datetime must be before end datetime"
        $this->assertStringContainsString('Start datetime must be before end datetime', $result->message);
    }

    public function test_passes_when_start_before_end(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_when_duration_is_positive(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
