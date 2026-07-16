<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records\Filters;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use DateTimeInterface;

final class ScheduleFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?string $title = null,
        public readonly ?ScheduleStatus $status = null,
        public readonly ?DateTimeInterface $start_datetime_from = null,
        public readonly ?DateTimeInterface $start_datetime_to = null,
        public readonly ?DateTimeInterface $end_datetime_from = null,
        public readonly ?DateTimeInterface $end_datetime_to = null,
        public readonly ?bool $withTrashed = false,
    ) {}
}
