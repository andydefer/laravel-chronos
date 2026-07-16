<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records\Filters;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use DateTimeInterface;

final class ImpedimentFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $reason = null,
        public readonly ?DateTimeInterface $start_datetime_from = null,
        public readonly ?DateTimeInterface $start_datetime_to = null,
        public readonly ?DateTimeInterface $end_datetime_from = null,
        public readonly ?DateTimeInterface $end_datetime_to = null,
        public readonly ?bool $withTrashed = false,
    ) {}
}
