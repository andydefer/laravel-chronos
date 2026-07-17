<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * @extends TypedCollection<ValidationErrorRecord>
 */
final class ValidationErrorCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(ValidationErrorRecord::class);
    }

    public function hasErrors(): bool
    {
        return $this->count() > 0;
    }

    public function getMessages(): array
    {
        return array_map(
            fn (ValidationErrorRecord $error): string => $error->message,
            $this->items
        );
    }

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

    public function getFirstError(): ?ValidationErrorRecord
    {
        return $this->items[0] ?? null;
    }
}
