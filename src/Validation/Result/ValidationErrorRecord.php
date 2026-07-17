<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Result;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Associative;

final class ValidationErrorRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $rule,
        public readonly string $message,
        public readonly ?Associative $context = null,
    ) {}
}
