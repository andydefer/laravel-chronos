<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Validation;

use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

interface ValidationRule
{
    /**
     * Check if this rule supports the given validation context.
     */
    public function supports(ValidationContext $context): bool;

    /**
     * Validate the context and return an error if validation fails.
     *
     * @return ValidationErrorRecord|null The error record or null if validation passes
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord;
}
