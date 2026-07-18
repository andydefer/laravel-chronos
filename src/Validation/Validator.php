<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Orchestrates validation rules for different entity types and operations.
 *
 * This validator manages a registry of validation rules organized by entity type.
 * When a validation is requested, it executes all registered rules that support
 * the given context and collects any validation errors.
 *
 * @example
 * $validator = new Validator();
 * $validator->addRule(EntityType::AVAILABILITY, new AvailabilityCreateRule());
 * $validator->addRule(EntityType::AVAILABILITY, new AvailabilityUpdateRule());
 *
 * $result = $validator->validateRecord($record, OperationType::CREATE);
 *
 * @see ValidatorInterface
 */
final class Validator implements ValidatorInterface
{
    /**
     * @var array<string, array<ValidationRule>> Registry of validation rules by entity type
     */
    private array $rules = [];

    /**
     * {@inheritDoc}
     */
    public function addRule(EntityType $entityType, ValidationRule $rule): self
    {
        $key = $entityType->value;

        if (! isset($this->rules[$key])) {
            $this->rules[$key] = [];
        }

        $this->rules[$key][] = $rule;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addRules(EntityType $entityType, array $rules): self
    {
        foreach ($rules as $rule) {
            $this->addRule($entityType, $rule);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(ValidationContext $context): ValidationResult
    {
        $entityTypeKey = $context->getEntityType()->value;
        $result = new ValidationResult;

        $rules = $this->getRulesForEntityKey($entityTypeKey);

        foreach ($rules as $rule) {
            if ($rule->supports($context)) {
                $error = $rule->validate($context);

                if ($error !== null) {
                    $result->addError($error);
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function validateRecord(
        AbstractRecord $record,
        OperationType $operation,
        ?Model $existingEntity = null,
    ): ValidationResult {
        $context = new ValidationContext($record, $operation, $existingEntity);

        return $this->validate($context);
    }

    /**
     * {@inheritDoc}
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * {@inheritDoc}
     */
    public function getRulesForEntity(EntityType $entityType): array
    {
        return $this->getRulesForEntityKey($entityType->value);
    }

    /**
     * {@inheritDoc}
     */
    public function hasRulesForEntity(EntityType $entityType): bool
    {
        $rules = $this->getRulesForEntityKey($entityType->value);

        return ! empty($rules);
    }

    /**
     * Retrieves rules for a specific entity type key.
     *
     * @param  string  $entityTypeKey  The entity type key (value from EntityType enum)
     * @return array<ValidationRule> Array of validation rules
     */
    private function getRulesForEntityKey(string $entityTypeKey): array
    {
        return $this->rules[$entityTypeKey] ?? [];
    }
}
