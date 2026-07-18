<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityDaysFormatRule;
use PHPUnit\Framework\TestCase;

final class AvailabilityDaysFormatRuleTest extends TestCase
{
    private AvailabilityDaysFormatRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AvailabilityDaysFormatRule;
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

    public function test_returns_error_when_days_are_null(): void
    {
        $record = new AvailabilityRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(AvailabilityDaysFormatRule::class, $result->rule);
        $this->assertStringContainsString('At least one day must be specified', $result->message);
    }

    public function test_returns_error_when_days_are_empty(): void
    {
        $record = new AvailabilityRecord(
            days: new WeekDayCollection
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('At least one day must be specified', $result->message);
    }

    public function test_returns_error_when_days_have_duplicates(): void
    {
        $days = WeekDayCollection::fromStrings(['monday', 'tuesday', 'monday']);
        $record = new AvailabilityRecord(days: $days);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Duplicate day(s) found: monday', $result->message);
    }

    public function test_passes_validation_for_valid_days_without_duplicates(): void
    {
        $days = WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday']);
        $record = new AvailabilityRecord(days: $days);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_validation_for_all_days_of_the_week(): void
    {
        $days = WeekDayCollection::fromStrings([
            'monday', 'tuesday', 'wednesday',
            'thursday', 'friday', 'saturday', 'sunday',
        ]);
        $record = new AvailabilityRecord(days: $days);
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
