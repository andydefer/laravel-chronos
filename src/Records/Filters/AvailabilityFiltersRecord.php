<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records\Filters;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class AvailabilityFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $name = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?WeekDayCollection $days = null,
        public readonly ?DateTimeZuluVO $validity_start = null,
        public readonly ?DateTimeZuluVO $validity_end = null,
        public readonly ?bool $withTrashed = false,
    ) {}
}
