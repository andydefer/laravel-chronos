<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use DateTimeInterface;

final class ScheduleRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?DateTimeInterface $start_datetime = null,
        public readonly ?DateTimeInterface $end_datetime = null,
        public readonly ?ScheduleStatus $status = null,
        public readonly ?array $metadata = null,
    ) {}
}
