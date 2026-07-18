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
 * and operations. The validator is responsible for registering rules
 * and executing them against validation contexts.
 *
 * @example
 * $validator = new Validator();
 * $validator->addRule(EntityType::AVAILABILITY, new AvailabilityRule());
 *
 * $result = $validator->validateRecord($record, OperationType::CREATE);
 * if ($result->hasErrors()) {
 *     // Handle validation errors
 * }
 */
interface ValidatorInterface
{
    /**
     * Adds a validation rule for a specific entity type.
     *
     * Rules are stored and executed in the order they are added.
     *
     * @param  EntityType  $entityType  The entity type to associate the rule with
     * @param  ValidationRule  $rule  The validation rule to add
     * @return self Returns the current instance for method chaining
     */
    public function addRule(EntityType $entityType, ValidationRule $rule): self;

    /**
     * Adds multiple validation rules for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type to associate the rules with
     * @param  array<ValidationRule>  $rules  Array of validation rules to add
     * @return self Returns the current instance for method chaining
     */
    public function addRules(EntityType $entityType, array $rules): self;

    /**
     * Validates a context against all registered rules.
     *
     * Executes all rules associated with the entity type in the context.
     * Rules that do not support the context are skipped.
     *
     * @param  ValidationContext  $context  The validation context containing record and operation data
     * @return ValidationResult The validation result containing any errors
     */
    public function validate(ValidationContext $context): ValidationResult;

    /**
     * Validates a record with a specific operation.
     *
     * Convenience method that creates a ValidationContext from the given parameters
     * and delegates to {@see validate()}.
     *
     * @param  AbstractRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation type (CREATE, UPDATE, DELETE)
     * @param  Model|null  $existingEntity  The existing entity for update/delete operations
     * @return ValidationResult The validation result containing any errors
     */
    public function validateRecord(
        AbstractRecord $record,
        OperationType $operation,
        ?Model $existingEntity = null,
    ): ValidationResult;

    /**
     * Gets all registered rules.
     *
     * @return array<string, array<ValidationRule>> Array of entity type keys to rule arrays
     */
    public function getRules(): array;

    /**
     * Gets rules for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type to retrieve rules for
     * @return array<ValidationRule> Array of validation rules for the entity type
     */
    public function getRulesForEntity(EntityType $entityType): array;

    /**
     * Checks if rules exist for a specific entity type.
     *
     * @param  EntityType  $entityType  The entity type to check
     * @return bool True if the entity type has at least one rule registered
     */
    public function hasRulesForEntity(EntityType $entityType): bool;
}
