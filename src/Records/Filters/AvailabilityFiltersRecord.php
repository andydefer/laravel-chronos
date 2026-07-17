<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records\Filters;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class AvailabilityFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $name = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?WeekDayCollection $days = null,
        public readonly ?DateTimeVO $validity_start = null,
        public readonly ?DateTimeVO $validity_end = null,
        public readonly ?bool $withTrashed = false,
    ) {}
}
