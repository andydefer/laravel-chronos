<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationResult;
use Illuminate\Database\Eloquent\Model;

final class Validator
{
    /**
     * @var array<string, array<ValidationRule>>
     */
    private array $rules = [];

    /**
     * Add a validation rule for a specific entity type.
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
     * Add multiple validation rules for a specific entity type.
     */
    public function addRules(EntityType $entityType, array $rules): self
    {
        foreach ($rules as $rule) {
            $this->addRule($entityType, $rule);
        }

        return $this;
    }

    /**
     * Validate a context against all registered rules.
     */
    public function validate(ValidationContext $context): ValidationResult
    {
        $key = $context->getEntityType()->value;
        $result = new ValidationResult;

        $rules = $this->rules[$key] ?? [];

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
     * Validate a record with a specific operation.
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
     * Get all registered rules.
     *
     * @return array<string, array<ValidationRule>>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get rules for a specific entity type.
     *
     * @return array<ValidationRule>
     */
    public function getRulesForEntity(EntityType $entityType): array
    {
        return $this->rules[$entityType->value] ?? [];
    }

    /**
     * Check if a rule exists for a specific entity type.
     */
    public function hasRulesForEntity(EntityType $entityType): bool
    {
        return isset($this->rules[$entityType->value]) && ! empty($this->rules[$entityType->value]);
    }
}
