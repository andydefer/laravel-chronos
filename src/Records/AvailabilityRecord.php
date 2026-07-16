<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use DateTimeInterface;

final class AvailabilityRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $name = null,
        public readonly ?string $daily_start = null,
        public readonly ?string $daily_end = null,
        public readonly ?string $schedulable_type = null,
        public readonly ?int $schedulable_id = null,
        public readonly ?array $days = null,
        public readonly ?DateTimeInterface $validity_start = null,
        public readonly ?DateTimeInterface $validity_end = null,
    ) {}
}
