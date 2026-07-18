<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ScheduleRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $availability_id = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?DateTimeZuluVO $start_datetime = null,
        public readonly ?DateTimeZuluVO $end_datetime = null,
        public readonly ?ScheduleStatus $status = null,
        public readonly ?Associative $metadata = null,
    ) {}
}
