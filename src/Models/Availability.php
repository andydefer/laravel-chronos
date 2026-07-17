<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\PhpVo\ValueObjects\TimeVO;
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
     * Get daily start time as TimeVO.
     */
    public function getDailyStart(): ?TimeVO
    {
        if ($this->daily_start === null) {
            return null;
        }

        return TimeVO::from($this->daily_start->format('H:i:s'));
    }

    /**
     * Get daily end time as TimeVO.
     */
    public function getDailyEnd(): ?TimeVO
    {
        if ($this->daily_end === null) {
            return null;
        }

        return TimeVO::from($this->daily_end->format('H:i:s'));
    }

    /**
     * Get validity start as DateTimeVO.
     */
    public function getValidityStart(): ?DateTimeVO
    {
        if ($this->validity_start === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->validity_start);
    }

    /**
     * Get validity end as DateTimeVO.
     */
    public function getValidityEnd(): ?DateTimeVO
    {
        if ($this->validity_end === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->validity_end);
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
