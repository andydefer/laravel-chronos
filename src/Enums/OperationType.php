<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Enums;

enum OperationType: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    /**
     * Check if the operation is a write operation (create or update).
     */
    public function isWrite(): bool
    {
        return $this === self::CREATE || $this === self::UPDATE;
    }

    /**
     * Get the label for this operation.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CREATE => 'Create',
            self::UPDATE => 'Update',
            self::DELETE => 'Delete',
        };
    }
}
