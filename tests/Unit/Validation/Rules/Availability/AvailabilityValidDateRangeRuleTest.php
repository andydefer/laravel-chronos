<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityValidDateRangeRule;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use PHPUnit\Framework\TestCase;

final class AvailabilityValidDateRangeRuleTest extends TestCase
{
    private AvailabilityValidDateRangeRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AvailabilityValidDateRangeRule;
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

    /**
     * Cross-day est autorisé (daily_start > daily_end)
     * Donc ce test vérifie que la règle ne retourne PAS d'erreur
     */
    public function test_passes_when_daily_start_is_after_daily_end_cross_day(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('17:00:00'),
            daily_end: TimeZuluVO::from('09:00:00'), // Cross-day : 17:00 > 09:00
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        // Cross-day est autorisé, donc pas d'erreur
        $this->assertNull($result);
    }

    /**
     * Non cross-day avec daily_start == daily_end doit retourner une erreur
     */
    public function test_returns_error_when_daily_start_equals_daily_end(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:00:00'), // Égal
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Daily start time must be before daily end time', $result->message);
    }

    /**
     * Non cross-day avec daily_start < daily_end doit passer
     */
    public function test_passes_when_daily_start_is_before_daily_end(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'), // 09:00 < 17:00
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_validity_start_is_after_validity_end(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-12-31T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-01T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Validity start date must be before validity end date', $result->message);
    }

    public function test_returns_error_when_validity_start_is_missing_on_create(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Validity start date is required', $result->message);
    }

    public function test_returns_error_when_validity_end_is_missing_on_create(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Validity end date is required', $result->message);
    }

    public function test_returns_error_when_validity_start_and_end_are_missing_on_create(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Validity start date is required', $result->message);
    }

    public function test_passes_when_validity_dates_are_missing_on_update(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            // Pas de validity_start et validity_end pour UPDATE
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        // Pour UPDATE, les dates de validité sont optionnelles
        $this->assertNull($result);
    }

    public function test_passes_validation_when_dates_are_valid(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_validation_for_update_when_validity_dates_are_valid(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
