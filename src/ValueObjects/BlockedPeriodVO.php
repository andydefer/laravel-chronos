<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use UnitEnum;

/**
 * Value Object representing a blocked period.
 *
 * Represents a time period that is blocked by a schedule or impediment.
 * Used to visualize and analyze conflicts in the scheduling system.
 *
 * @example
 * $period = new BlockedPeriodVO(
 *     DateTimeZuluVO::from('2024-01-15T09:30:00Z'),
 *     DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
 *     'schedule',
 *     42
 * );
 *
 * echo $period->getType(); // 'schedule'
 * echo $period->getDuration(); // 30
 */
final class BlockedPeriodVO extends AbstractValueObject
{
    /**
     * @param  DateTimeZuluVO  $start  The start of the blocked period
     * @param  DateTimeZuluVO  $end  The end of the blocked period
     * @param  string  $type  The type of blocker ('schedule' or 'impediment')
     * @param  int  $id  The ID of the blocker entity
     */
    public function __construct(
        public readonly DateTimeZuluVO $start,
        public readonly DateTimeZuluVO $end,
        public readonly string $type,
        public readonly int $id,
    ) {}

    /**
     * Gets the start of the blocked period.
     *
     * @return DateTimeZuluVO The start datetime
     */
    public function getStart(): DateTimeZuluVO
    {
        return $this->start;
    }

    /**
     * Gets the end of the blocked period.
     *
     * @return DateTimeZuluVO The end datetime
     */
    public function getEnd(): DateTimeZuluVO
    {
        return $this->end;
    }

    /**
     * Gets the type of blocker.
     *
     * @return string The blocker type ('schedule' or 'impediment')
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the ID of the blocker entity.
     *
     * @return int The entity ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Calculates the duration of the blocked period in minutes.
     *
     * @return int The duration in minutes
     */
    public function getDurationInMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * Checks if the blocked period overlaps with a given time range.
     *
     * @param  DateTimeZuluVO  $start  The start of the range to check
     * @param  DateTimeZuluVO  $end  The end of the range to check
     * @return bool True if the periods overlap
     */
    public function overlapsWith(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        return $this->start->isBefore($end) && $this->end->isAfter($start);
    }

    /**
     * Checks if the blocked period contains a given datetime.
     *
     * @param  DateTimeZuluVO  $datetime  The datetime to check
     * @return bool True if the datetime is within the blocked period
     */
    public function contains(DateTimeZuluVO $datetime): bool
    {
        return ($this->start->isBefore($datetime) || $this->start->isEqual($datetime))
            && ($this->end->isAfter($datetime) || $this->end->isEqual($datetime));
    }

    /**
     * Checks if the blocker is a schedule.
     *
     * @return bool True if the blocker is a schedule
     */
    public function isSchedule(): bool
    {
        return $this->type === 'schedule';
    }

    /**
     * Checks if the blocker is an impediment.
     *
     * @return bool True if the blocker is an impediment
     */
    public function isImpediment(): bool
    {
        return $this->type === 'impediment';
    }

    public function getValue(): int|string|float|bool|null|UnitEnum|Transformable
    {
        return StrictAssociative::from($this->toArray());
    }

    /**
     * Converts the blocked period to an array.
     *
     * @return array{start: string, end: string, type: string, id: int, duration: int}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateTimeString(),
            'end' => $this->end->toDateTimeString(),
            'type' => $this->type,
            'id' => $this->id,
            'duration' => $this->getDurationInMinutes(),
        ];
    }
}
