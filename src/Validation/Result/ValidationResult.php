<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Result;

use AndyDefer\LaravelChronos\Validation\Collections\ValidationErrorCollection;

final class ValidationResult
{
    private ValidationErrorCollection $errors;

    public function __construct()
    {
        $this->errors = new ValidationErrorCollection;
    }

    public function addError(ValidationErrorRecord $error): self
    {
        $this->errors->add($error);

        return $this;
    }

    public function hasErrors(): bool
    {
        return $this->errors->hasErrors();
    }

    public function getErrors(): ValidationErrorCollection
    {
        return $this->errors;
    }

    public function getMessages(): array
    {
        return $this->errors->getMessages();
    }

    public function isEmpty(): bool
    {
        return ! $this->hasErrors();
    }
}
