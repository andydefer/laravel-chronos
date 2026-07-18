<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\MaxDurationRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class MaxDurationRuleTest extends IntegrationTestCase
{
    private MaxDurationRule $rule;

    private ChronosConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chronos.min_duration', 15);
        config()->set('chronos.max_duration', 240);
        config()->set('chronos.buffer_time', 0);

        $helper = new ValidationHelperService;
        $this->config = new ChronosConfig(app(ConfigRepository::class));
        $this->rule = new MaxDurationRule($helper, $this->config);
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

    public function test_returns_error_when_duration_exceeds_maximum(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T15:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(MaxDurationRule::class, $result->rule);
        $this->assertStringContainsString('exceeds maximum allowed duration', $result->message);
        $this->assertStringContainsString('6 hours', $result->message);
    }

    public function test_returns_error_when_duration_exceeds_maximum_with_minutes(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T13:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('4 hours 30 minutes', $result->message);
    }

    public function test_passes_when_duration_equals_maximum(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T13:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_when_duration_under_maximum(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T12:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_respects_configuration_changes(): void
    {
        config()->set('chronos.max_duration', 60);

        $helper = new ValidationHelperService;
        $config = new ChronosConfig(app(ConfigRepository::class));
        $rule = new MaxDurationRule($helper, $config);

        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('1 hours 30 minutes', $result->message);

        config()->set('chronos.max_duration', 240);
    }
}
