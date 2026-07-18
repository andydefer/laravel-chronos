<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\CrossDayAvailabilityRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use PHPUnit\Framework\TestCase;

final class CrossDayAvailabilityRuleTest extends TestCase
{
    private CrossDayAvailabilityRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $helper = new ValidationHelperService;
        $this->rule = new CrossDayAvailabilityRule($helper);
    }

    public function test_supports_availability_create_and_update_operations(): void
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

    public function test_returns_null_when_required_data_is_missing(): void
    {
        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_for_non_cross_day_availability(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            days: WeekDayCollection::fromStrings(['monday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_for_cross_day_with_consecutive_days(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday', 'tuesday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_for_cross_day_with_non_consecutive_days(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday', 'wednesday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability crosses midnight but days array is not consecutive', $result->message);
        $this->assertStringContainsString('monday, wednesday', $result->message);
    }

    public function test_passes_for_cross_day_with_weekend_consecutive_days(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['friday', 'saturday', 'sunday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_for_cross_day_with_single_day(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability crosses midnight but days array is not consecutive', $result->message);
    }
}
