<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Context;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\OperationType;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Context object for validation operations.
 *
 * Encapsulates all the data needed to validate a record: the record itself,
 * the operation being performed, and the existing entity (for updates and deletes).
 *
 * @example
 * $context = new ValidationContext($record, OperationType::CREATE);
 * if ($context->isCreate()) {
 *     // Handle create-specific validation
 * }
 *
 * @immutable This class is designed to be immutable
 */
final class ValidationContext
{
    private readonly EntityType $entityType;

    /**
     * @param  AbstractRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation being performed
     * @param  Model|null  $existingEntity  The existing entity for update/delete operations
     *
     * @throws InvalidArgumentException When the record type is not supported
     */
    public function __construct(
        private readonly AbstractRecord $record,
        private readonly OperationType $operation,
        private readonly ?Model $existingEntity = null,
    ) {
        $this->entityType = EntityType::fromRecord($record)
            ?? throw new InvalidArgumentException(
                'Unsupported record type: '.get_class($record)
            );
    }

    /**
     * Returns the record being validated.
     *
     * @return AbstractRecord The record instance
     */
    public function getRecord(): AbstractRecord
    {
        return $this->record;
    }

    /**
     * Returns the operation being performed.
     *
     * @return OperationType The operation type (CREATE, UPDATE, DELETE)
     */
    public function getOperation(): OperationType
    {
        return $this->operation;
    }

    /**
     * Returns the entity type being validated.
     *
     * @return EntityType The entity type (AVAILABILITY, SCHEDULE, IMPEDIMENT)
     */
    public function getEntityType(): EntityType
    {
        return $this->entityType;
    }

    /**
     * Returns the existing entity for update/delete operations.
     *
     * @return Model|null The existing entity or null if not applicable
     */
    public function getExistingEntity(): ?Model
    {
        return $this->existingEntity;
    }

    /**
     * Checks if the operation is CREATE.
     *
     * @return bool True if the operation is CREATE
     */
    public function isCreate(): bool
    {
        return $this->operation === OperationType::CREATE;
    }

    /**
     * Checks if the operation is UPDATE.
     *
     * @return bool True if the operation is UPDATE
     */
    public function isUpdate(): bool
    {
        return $this->operation === OperationType::UPDATE;
    }

    /**
     * Checks if the operation is DELETE.
     *
     * @return bool True if the operation is DELETE
     */
    public function isDelete(): bool
    {
        return $this->operation === OperationType::DELETE;
    }

    /**
     * Checks if an existing entity is present.
     *
     * @return bool True if the context has an existing entity
     */
    public function hasExistingEntity(): bool
    {
        return $this->existingEntity !== null;
    }
}
