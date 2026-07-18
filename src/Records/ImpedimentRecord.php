<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ImpedimentRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $reason = null,
        public readonly ?DateTimeZuluVO $start_datetime = null,
        public readonly ?DateTimeZuluVO $end_datetime = null,
        public readonly ?Associative $metadata = null,
    ) {}
}
