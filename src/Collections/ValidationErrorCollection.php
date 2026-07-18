<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * Collection of validation error records.
 *
 * Provides specialized methods for working with validation errors,
 * including filtering by field, rule, and retrieving error messages.
 *
 * @extends TypedCollection<ValidationErrorRecord>
 *
 * @example
 * $errors = new ValidationErrorCollection();
 * $errors->add(new ValidationErrorRecord('email', 'Invalid email', 'email'));
 *
 * if ($errors->hasErrorFor('email')) {
 *     $emailErrors = $errors->filterByField('email');
 * }
 */
final class ValidationErrorCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ValidationErrorRecord::class);
    }

    /**
     * Checks if there are any errors in the collection.
     *
     * @return bool True if the collection contains at least one error
     */
    public function hasErrors(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Extracts all error messages as a flat array.
     *
     * @return array<int, string> Array of error messages
     */
    public function getMessages(): array
    {
        return array_map(
            fn (ValidationErrorRecord $error): string => $error->message,
            $this->items
        );
    }

    /**
     * Filters errors by a specific rule name.
     *
     * @param  string  $rule  The rule name to filter by
     * @return self A new collection containing only errors with the specified rule
     */
    public function getErrorsByRule(string $rule): self
    {
        $collection = new self;
        foreach ($this->items as $error) {
            if ($error->rule === $rule) {
                $collection->add($error);
            }
        }

        return $collection;
    }

    /**
     * Filters errors by a specific field name.
     *
     * @param  string  $field  The field name to filter by
     * @return self A new collection containing only errors for the specified field
     */
    public function filterByField(string $field): self
    {
        $collection = new self;
        foreach ($this->items as $error) {
            if ($error->field === $field) {
                $collection->add($error);
            }
        }

        return $collection;
    }

    /**
     * Checks if there are errors for a specific field.
     *
     * @param  string  $field  The field name to check
     * @return bool True if the field has errors, false otherwise
     */
    public function hasErrorFor(string $field): bool
    {
        foreach ($this->items as $error) {
            if ($error->field === $field) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the first error in the collection.
     *
     * @return ValidationErrorRecord|null The first error or null if collection is empty
     */
    public function getFirstError(): ?ValidationErrorRecord
    {
        return $this->items[0] ?? null;
    }

    /**
     * Gets the first error for a specific field.
     *
     * @param  string  $field  The field name to get the first error for
     * @return ValidationErrorRecord|null The first error for the field or null if none exist
     */
    public function getFirstErrorFor(string $field): ?ValidationErrorRecord
    {
        foreach ($this->items as $error) {
            if ($error->field === $field) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Groups errors by field name.
     *
     * @return array<string, self> Associative array of field names to error collections
     */
    public function groupByField(): array
    {
        $grouped = [];

        foreach ($this->items as $error) {
            $field = $error->field;

            if (! isset($grouped[$field])) {
                $grouped[$field] = new self;
            }

            $grouped[$field]->add($error);
        }

        return $grouped;
    }

    /**
     * Gets all unique field names that have errors.
     *
     * @return array<int, string> Array of field names
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->items as $error) {
            if (! in_array($error->field, $fields, true)) {
                $fields[] = $error->field;
            }
        }

        return $fields;
    }

    /**
     * Gets all unique rule names used in the errors.
     *
     * @return array<int, string> Array of rule names
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->items as $error) {
            if ($error->rule !== null && ! in_array($error->rule, $rules, true)) {
                $rules[] = $error->rule;
            }
        }

        return $rules;
    }
}
