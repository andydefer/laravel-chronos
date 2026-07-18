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

final class Validator implements ValidatorInterface
{
    /**
     * @var array<string, array<ValidationRule>>
     */
    private array $rules = [];

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function addRules(EntityType $entityType, array $rules): self
    {
        foreach ($rules as $rule) {
            $this->addRule($entityType, $rule);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * {@inheritdoc}
     */
    public function getRulesForEntity(EntityType $entityType): array
    {
        return $this->rules[$entityType->value] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasRulesForEntity(EntityType $entityType): bool
    {
        return isset($this->rules[$entityType->value]) && ! empty($this->rules[$entityType->value]);
    }
}
