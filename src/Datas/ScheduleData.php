<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

final class ScheduleData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly int $availabilityId,
        public readonly ?string $schedulableType,
        public readonly ?int $schedulableId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly DateTimeZuluVO $startDatetime,
        public readonly DateTimeZuluVO $endDatetime,
        public readonly ScheduleStatus $status,
        public readonly ?StrictAssociative $metadata,
        public readonly ?bool $isActive = null,
        public readonly ?bool $isUpcoming = null,
        public readonly ?bool $isPast = null,
        public readonly ?bool $isCrossDay = null,
        public readonly ?bool $isSameDay = null,
        public readonly ?bool $isSameHour = null,
        public readonly ?int $durationInMinutes = null,
        public readonly ?bool $canBeCancelled = null,
        public readonly ?bool $canBeCompleted = null,
    ) {}
}
