<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Result;

use AndyDefer\LaravelChronos\Collections\ValidationErrorCollection;

/**
 * Represents the result of a validation operation.
 *
 * This immutable-like object holds validation errors and provides convenient
 * methods to check for errors and retrieve error messages. It follows a
 * builder pattern for adding errors and returns itself for method chaining.
 *
 * @example
 * $result = new ValidationResult();
 * $result->addError(new ValidationErrorRecord('email', 'Invalid email format'));
 *
 * if ($result->hasErrors()) {
 *     $messages = $result->getMessages();
 *     // Handle validation failures
 * }
 *
 * // Check for specific field errors
 * if ($result->hasErrorFor('email')) {
 *     $emailErrors = $result->getErrorsFor('email');
 * }
 */
final class ValidationResult
{
    private ValidationErrorCollection $errors;

    public function __construct(?ValidationErrorCollection $initialErrors = null)
    {
        $this->errors = $initialErrors ?? new ValidationErrorCollection;
    }

    /**
     * Adds a validation error to the result.
     *
     * @param  ValidationErrorRecord  $error  The error record to add
     * @return self Returns the current instance for method chaining
     */
    public function addError(ValidationErrorRecord $error): self
    {
        $this->errors->add($error);

        return $this;
    }

    /**
     * Merges another validation result into this one.
     *
     * Combines the errors from the other result with the current errors.
     *
     * @param  ValidationResult  $other  The other validation result to merge
     * @return self Returns the current instance for method chaining
     */
    public function merge(ValidationResult $other): self
    {
        foreach ($other->getErrors() as $error) {
            $this->addError($error);
        }

        return $this;
    }

    /**
     * Checks if there are any validation errors.
     *
     * @return bool True if the result contains errors, false otherwise
     */
    public function hasErrors(): bool
    {
        return $this->errors->hasErrors();
    }

    /**
     * Checks if there are validation errors for a specific field.
     *
     * @param  string  $field  The field name to check
     * @return bool True if the field has errors, false otherwise
     */
    public function hasErrorFor(string $field): bool
    {
        return $this->errors->hasErrorFor($field);
    }

    /**
     * Returns the collection of validation errors.
     *
     * @return ValidationErrorCollection The collection of error records
     */
    public function getErrors(): ValidationErrorCollection
    {
        return $this->errors;
    }

    /**
     * Returns all error messages as a flat array.
     *
     * @return array<int, string> Array of error messages
     */
    public function getMessages(): array
    {
        return $this->errors->getMessages();
    }

    /**
     * Returns validation errors for a specific field.
     *
     * @param  string  $field  The field name to retrieve errors for
     * @return ValidationErrorCollection Collection of errors for the field
     */
    public function getErrorsFor(string $field): ValidationErrorCollection
    {
        return $this->errors->filterByField($field);
    }

    /**
     * Gets the first error message for a specific field.
     *
     * Convenience method to quickly get the first error message for a field.
     *
     * @param  string  $field  The field name
     * @return string|null The first error message or null if no errors exist
     */
    public function getFirstErrorFor(string $field): ?string
    {
        $errors = $this->getErrorsFor($field);

        if ($errors->isEmpty()) {
            return null;
        }

        $firstError = $errors->first();

        return $firstError !== null ? $firstError->getMessage() : null;
    }

    /**
     * Checks if the validation result contains no errors.
     *
     * Convenience method that negates {@see hasErrors()}.
     *
     * @return bool True if there are no errors, false otherwise
     */
    public function isEmpty(): bool
    {
        return ! $this->hasErrors();
    }

    /**
     * Counts the total number of errors.
     *
     * @return int The number of errors
     */
    public function count(): int
    {
        return $this->errors->count();
    }

    /**
     * Creates a successful validation result.
     *
     * Factory method for creating a validation result with no errors.
     *
     * @return self A new validation result with no errors
     */
    public static function success(): self
    {
        return new self;
    }

    /**
     * Creates a failed validation result with errors.
     *
     * Factory method for creating a validation result with errors.
     *
     * @param  ValidationErrorRecord|array<ValidationErrorRecord>  $errors  The error(s) to include
     * @return self A new validation result with the specified errors
     */
    public static function failure(ValidationErrorRecord|array $errors): self
    {
        $result = new self;

        if ($errors instanceof ValidationErrorRecord) {
            $result->addError($errors);
        } else {
            foreach ($errors as $error) {
                $result->addError($error);
            }
        }

        return $result;
    }
}
