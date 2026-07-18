<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a model is not found.
 */
final class ModelNotFoundException extends RuntimeException
{
    public function __construct(string $model, int|string $id, int $code = 404)
    {
        parent::__construct(
            sprintf('%s with ID #%s not found.', class_basename($model), (string) $id),
            $code
        );
    }

    /**
     * Create a ModelNotFoundException for a specific model and ID.
     *
     * @param  string  $model  The model class name
     * @param  int|string  $id  The model ID
     */
    public static function create(string $model, int|string $id): self
    {
        return new self($model, $id);
    }
}
