<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

final class AvailabilityData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $type,
        public readonly ?string $name,
        public readonly ?TimeZuluVO $dailyStart,
        public readonly ?TimeZuluVO $dailyEnd,
        public readonly ?string $schedulableType,
        public readonly ?int $schedulableId,
        public readonly ?WeekDayCollection $days,
        public readonly ?DateTimeZuluVO $validityStart,
        public readonly ?DateTimeZuluVO $validityEnd,
        public readonly ?bool $hasSchedules = null,
        public readonly ?bool $hasImpediments = null,
        public readonly ?bool $isCrossDay = null,
        public readonly ?bool $isSameDay = null,
        public readonly ?bool $isSameHour = null,
        public readonly ?int $durationInMinutes = null,
    ) {}
}
