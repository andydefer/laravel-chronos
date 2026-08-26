<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ImpedimentData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly int $availabilityId,
        public readonly string $reason,
        public readonly DateTimeZuluVO $startDatetime,
        public readonly DateTimeZuluVO $endDatetime,
        public readonly ?StrictAssociative $metadata,
    ) {}
}
