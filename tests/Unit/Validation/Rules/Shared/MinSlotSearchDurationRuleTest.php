<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\MinSlotSearchDurationRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class MinSlotSearchDurationRuleTest extends IntegrationTestCase
{
    private MinSlotSearchDurationRule $rule;

    private ChronosConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Set configuration
        config()->set('chronos.min_durations.slot_search', 5);
        config()->set('chronos.max_duration', 240);
        config()->set('chronos.buffer_time', 0);

        $this->config = new ChronosConfig(app(ConfigRepository::class));
        $this->rule = new MinSlotSearchDurationRule($this->config);
    }

    public function test_supports_schedule_and_impediment_create_and_update_operations(): void
    {
        $scheduleCreate = new ValidationContext(
            new ScheduleRecord,
            OperationType::CREATE
        );

        $scheduleUpdate = new ValidationContext(
            new ScheduleRecord,
            OperationType::UPDATE
        );

        $scheduleDelete = new ValidationContext(
            new ScheduleRecord,
            OperationType::DELETE
        );

        $this->assertTrue($this->rule->supports($scheduleCreate));
        $this->assertTrue($this->rule->supports($scheduleUpdate));
        $this->assertFalse($this->rule->supports($scheduleDelete));
    }

    public function test_returns_null_when_time_data_is_missing(): void
    {
        $record = new ScheduleRecord(
            availability_id: 1,
            title: 'Test Schedule'
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_duration_is_below_minimum(): void
    {
        $startDatetime = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $endDatetime = DateTimeZuluVO::from('2024-01-15T09:01:00Z'); // 1 minute

        $record = new ScheduleRecord(
            availability_id: 1,
            title: 'Test Schedule',
            start_datetime: $startDatetime,
            end_datetime: $endDatetime
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(MinSlotSearchDurationRule::class, $result->rule);
        $this->assertStringContainsString('Slot duration (1 minutes) is too short', $result->message);
        $this->assertStringContainsString('Minimum allowed duration for slot search is 5 minutes', $result->message);
    }

    public function test_passes_validation_when_duration_meets_minimum(): void
    {
        $startDatetime = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $endDatetime = DateTimeZuluVO::from('2024-01-15T09:30:00Z'); // 30 minutes

        $record = new ScheduleRecord(
            availability_id: 1,
            title: 'Test Schedule',
            start_datetime: $startDatetime,
            end_datetime: $endDatetime
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_validation_when_duration_exceeds_minimum(): void
    {
        $startDatetime = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $endDatetime = DateTimeZuluVO::from('2024-01-15T17:00:00Z'); // 480 minutes

        $record = new ScheduleRecord(
            availability_id: 1,
            title: 'Test Schedule',
            start_datetime: $startDatetime,
            end_datetime: $endDatetime
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_respects_configuration_changes(): void
    {
        // Change min duration to 30 minutes
        config()->set('chronos.min_durations.slot_search', 30);

        $config = new ChronosConfig(app(ConfigRepository::class));
        $rule = new MinSlotSearchDurationRule($config);

        $startDatetime = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $endDatetime = DateTimeZuluVO::from('2024-01-15T09:15:00Z'); // 15 minutes < 30 min

        $record = new ScheduleRecord(
            availability_id: 1,
            title: 'Test Schedule',
            start_datetime: $startDatetime,
            end_datetime: $endDatetime
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Slot duration (15 minutes) is too short', $result->message);
        $this->assertStringContainsString('Minimum allowed duration for slot search is 30 minutes', $result->message);

        // Restore configuration for other tests
        config()->set('chronos.min_durations.slot_search', 5);
    }

    public function test_applies_to_impediment_records(): void
    {
        $startDatetime = DateTimeZuluVO::from('2024-01-15T09:00:00Z');
        $endDatetime = DateTimeZuluVO::from('2024-01-15T09:01:00Z'); // 1 minute

        $record = new ImpedimentRecord(
            availability_id: 1,
            reason: 'Test Impediment',
            start_datetime: $startDatetime,
            end_datetime: $endDatetime
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Slot duration (1 minutes) is too short', $result->message);
    }
}
