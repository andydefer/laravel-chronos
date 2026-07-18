<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Exceptions;

use AndyDefer\LaravelChronos\Validation\Result\ValidationResult;
use RuntimeException;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @var array<string> The validation error messages
     */
    private array $errors;

    /**
     * @param  array<string>  $errors  The validation error messages
     */
    public function __construct(array $errors, string $message = 'Validation failed.', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create a ValidationException from a ValidationResult.
     *
     * @param  ValidationResult  $result  The validation result
     */
    public static function fromValidationResult(ValidationResult $result): self
    {
        return new self(
            $result->getMessages(),
            'Validation failed: '.implode('; ', $result->getMessages())
        );
    }
}
