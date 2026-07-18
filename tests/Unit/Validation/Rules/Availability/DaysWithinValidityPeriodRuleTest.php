<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\DaysWithinValidityPeriodRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use PHPUnit\Framework\TestCase;

final class DaysWithinValidityPeriodRuleTest extends TestCase
{
    private DaysWithinValidityPeriodRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $helper = new ValidationHelperService;
        $this->rule = new DaysWithinValidityPeriodRule($helper);
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

    public function test_skip_validation_for_perpetual_availability(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_day_not_in_validity_period(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['saturday', 'sunday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-05T23:59:59Z'), // Mon-Fri only
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('saturday, sunday', $result->message);
        $this->assertStringContainsString('not within the validity period', $result->message);
    }

    public function test_returns_error_when_single_day_not_in_validity_period(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['sunday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-05T23:59:59Z'), // Mon-Fri only
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('sunday', $result->message);
        $this->assertStringContainsString('not within the validity period', $result->message);
    }

    public function test_passes_when_days_are_within_validity_period(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-05T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_for_full_week_within_validity_period(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings([
                'monday', 'tuesday', 'wednesday',
                'thursday', 'friday', 'saturday', 'sunday',
            ]),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-07T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
