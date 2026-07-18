<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityRequiredFieldsRule;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use PHPUnit\Framework\TestCase;

final class AvailabilityRequiredFieldsRuleTest extends TestCase
{
    private AvailabilityRequiredFieldsRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new AvailabilityRequiredFieldsRule;
    }

    public function test_supports_only_availability_create_operations(): void
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
        $this->assertFalse($this->rule->supports($updateContext));
        $this->assertFalse($this->rule->supports($deleteContext));
    }

    public function test_returns_error_when_name_is_missing(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(AvailabilityRequiredFieldsRule::class, $result->rule);
        $this->assertStringContainsString('name', $result->message);
        $this->assertStringContainsString('required', $result->message);
    }

    public function test_returns_error_when_multiple_fields_are_missing(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test',
            daily_start: TimeZuluVO::from('09:00:00'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('daily_end', $result->message);
        $this->assertStringContainsString('schedulable_type', $result->message);
        $this->assertStringContainsString('schedulable_id', $result->message);
    }

    public function test_passes_validation_when_all_fields_are_present(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_does_not_support_update_operation(): void
    {
        // La règle ne supporte pas UPDATE
        // On vérifie que supports() retourne false
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            // Les autres champs sont manquants mais cela n'a pas d'importance
            // car la règle n'est pas supportée pour UPDATE
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        // Vérifier que supports() retourne false
        $this->assertFalse($this->rule->supports($context));

        // Si la règle n'est pas supportée, on ne doit pas appeler validate()
        // Le Validator ne l'appellera pas non plus
        // Donc le comportement attendu est que la règle ne soit pas exécutée
    }
}
