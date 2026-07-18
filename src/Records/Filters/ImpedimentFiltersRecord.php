<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records\Filters;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ImpedimentFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $reason = null,
        public readonly ?DateTimeZuluVO $start_datetime_from = null,
        public readonly ?DateTimeZuluVO $start_datetime_to = null,
        public readonly ?DateTimeZuluVO $end_datetime_from = null,
        public readonly ?DateTimeZuluVO $end_datetime_to = null,
        public readonly ?bool $withTrashed = false,
    ) {}
}
