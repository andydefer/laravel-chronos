<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Context;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\OperationType;
use Illuminate\Database\Eloquent\Model;

final class ValidationContext
{
    private EntityType $entityType;

    public function __construct(
        private readonly AbstractRecord $record,
        private readonly OperationType $operation,
        private readonly ?Model $existingEntity = null,
    ) {
        $this->entityType = EntityType::fromRecord($record)
            ?? throw new \InvalidArgumentException('Unsupported record type: '.get_class($record));
    }

    public function getRecord(): AbstractRecord
    {
        return $this->record;
    }

    public function getOperation(): OperationType
    {
        return $this->operation;
    }

    public function getEntityType(): EntityType
    {
        return $this->entityType;
    }

    public function getExistingEntity(): ?Model
    {
        return $this->existingEntity;
    }

    public function isCreate(): bool
    {
        return $this->operation === OperationType::CREATE;
    }

    public function isUpdate(): bool
    {
        return $this->operation === OperationType::UPDATE;
    }

    public function isDelete(): bool
    {
        return $this->operation === OperationType::DELETE;
    }

    public function hasExistingEntity(): bool
    {
        return $this->existingEntity !== null;
    }
}
