<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Associative;
use DateTimeInterface;

final class ImpedimentRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $reason = null,
        public readonly ?DateTimeInterface $start_datetime = null,
        public readonly ?DateTimeInterface $end_datetime = null,
        public readonly ?Associative $metadata = null,
    ) {}
}
