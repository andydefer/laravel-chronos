<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Validation;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for the validation orchestrator.
 *
 * Manages and executes validation rules for different entity types
 * and operations.
 */
interface ValidatorInterface
{
    /**
     * Add a validation rule for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type
     * @param  ValidationRule  $rule  The validation rule
     */
    public function addRule(EntityType $entityType, ValidationRule $rule): self;

    /**
     * Add multiple validation rules for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type
     * @param  array<ValidationRule>  $rules  Array of validation rules
     */
    public function addRules(EntityType $entityType, array $rules): self;

    /**
     * Validate a context against all registered rules.
     *
     * @param  ValidationContext  $context  The validation context
     * @return ValidationResult The validation result
     */
    public function validate(ValidationContext $context): ValidationResult;

    /**
     * Validate a record with a specific operation.
     *
     * @param  AbstractRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation type
     * @param  Model|null  $existingEntity  The existing entity (for updates)
     * @return ValidationResult The validation result
     */
    public function validateRecord(
        AbstractRecord $record,
        OperationType $operation,
        ?Model $existingEntity = null,
    ): ValidationResult;

    /**
     * Get all registered rules.
     *
     * @return array<string, array<ValidationRule>>
     */
    public function getRules(): array;

    /**
     * Get rules for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type
     * @return array<ValidationRule> Array of validation rules
     */
    public function getRulesForEntity(EntityType $entityType): array;

    /**
     * Check if rules exist for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type
     * @return bool True if rules exist
     */
    public function hasRulesForEntity(EntityType $entityType): bool;
}
