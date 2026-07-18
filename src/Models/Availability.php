<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Availability extends Model
{
    use SoftDeletes;

    protected $table = 'availabilities';

    protected $fillable = [
        'type',
        'name',
        'daily_start',
        'daily_end',
        'schedulable_type',
        'schedulable_id',
        'days',
        'validity_start',
        'validity_end',
    ];

    protected $casts = [
        'days' => 'array',
        'validity_start' => 'datetime',
        'validity_end' => 'datetime',
        'daily_start' => 'datetime:H:i:s',
        'daily_end' => 'datetime:H:i:s',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get daily start time as TimeZuluVO.
     */
    public function getDailyStart(): ?TimeZuluVO
    {
        if ($this->daily_start === null) {
            return null;
        }

        return TimeZuluVO::from($this->daily_start->format('H:i:s'));
    }

    /**
     * Get daily end time as TimeZuluVO.
     */
    public function getDailyEnd(): ?TimeZuluVO
    {
        if ($this->daily_end === null) {
            return null;
        }

        return TimeZuluVO::from($this->daily_end->format('H:i:s'));
    }

    /**
     * Get validity start as DateTimeZuluVO.
     */
    public function getValidityStart(): ?DateTimeZuluVO
    {
        if ($this->validity_start === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->validity_start);
    }

    /**
     * Get validity end as DateTimeZuluVO.
     */
    public function getValidityEnd(): ?DateTimeZuluVO
    {
        if ($this->validity_end === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->validity_end);
    }

    /**
     * Get days as WeekDayCollection.
     */
    public function getDays(): WeekDayCollection
    {
        $collection = new WeekDayCollection;

        if ($this->days === null || ! is_array($this->days)) {
            return $collection;
        }

        foreach ($this->days as $day) {
            $enum = WeekDay::fromString($day);
            if ($enum !== null) {
                $collection->add($enum);
            }
        }

        return $collection;
    }
}
