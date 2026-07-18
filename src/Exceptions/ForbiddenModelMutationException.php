<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Exceptions;

use LogicException;

/**
 * Exception thrown when direct model mutation is attempted outside authorized contexts.
 */
final class ForbiddenModelMutationException extends LogicException
{
    public static function create(string $model, ?string $contextId = null): self
    {
        $message = sprintf(
            'Direct mutation of %s is forbidden. Use repository services.',
            class_basename($model)
        );

        if ($contextId) {
            $message .= sprintf(' Context: %s', $contextId);
        }

        return new self($message);
    }

    public static function createWithOperation(string $model, string $operation): self
    {
        return new self(
            sprintf(
                'Direct %s operation on %s is forbidden. Use repository services.',
                $operation,
                class_basename($model)
            )
        );
    }
}
