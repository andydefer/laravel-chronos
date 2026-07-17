<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Enums;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use Illuminate\Database\Eloquent\Model;

enum EntityType: string
{
    case AVAILABILITY = 'availability';
    case SCHEDULE = 'schedule';
    case IMPEDIMENT = 'impediment';

    /**
     * Get the EntityType from a Record instance.
     */
    public static function fromRecord(AbstractRecord $record): ?self
    {
        return match (true) {
            $record instanceof AvailabilityRecord => self::AVAILABILITY,
            $record instanceof ScheduleRecord => self::SCHEDULE,
            $record instanceof ImpedimentRecord => self::IMPEDIMENT,
            default => null,
        };
    }

    /**
     * Get the Record class name for this entity type.
     *
     * @return class-string<AbstractRecord>
     */
    public function getRecordClass(): string
    {
        return match ($this) {
            self::AVAILABILITY => AvailabilityRecord::class,
            self::SCHEDULE => ScheduleRecord::class,
            self::IMPEDIMENT => ImpedimentRecord::class,
        };
    }

    /**
     * Get the Model class name for this entity type.
     *
     * @return class-string<Model>
     */
    public function getModelClass(): string
    {
        return match ($this) {
            self::AVAILABILITY => Availability::class,
            self::SCHEDULE => Schedule::class,
            self::IMPEDIMENT => Impediment::class,
        };
    }

    /**
     * Get the label for this entity type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABILITY => 'Availability',
            self::SCHEDULE => 'Schedule',
            self::IMPEDIMENT => 'Impediment',
        };
    }

    /**
     * Check if this entity type can have schedules.
     */
    public function hasSchedules(): bool
    {
        return $this === self::AVAILABILITY;
    }

    /**
     * Check if this entity type can have impediments.
     */
    public function hasImpediments(): bool
    {
        return $this === self::AVAILABILITY;
    }

    /**
     * Check if this entity type is a child of an availability.
     */
    public function isChild(): bool
    {
        return $this === self::SCHEDULE || $this === self::IMPEDIMENT;
    }
}
