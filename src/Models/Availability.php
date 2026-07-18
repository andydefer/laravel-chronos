<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Get the schedulable entity (polymorphic).
     */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the schedules for this availability.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Get the impediments for this availability.
     */
    public function impediments(): HasMany
    {
        return $this->hasMany(Impediment::class);
    }

    /**
     * Get active schedules (not cancelled or completed).
     */
    public function activeSchedules(): HasMany
    {
        return $this->schedules()
            ->whereIn('status', [
                ScheduleStatus::BOOKED->value,
                ScheduleStatus::AVAILABLE->value,
            ]);
    }

    /**
     * Get upcoming schedules (start_datetime > now).
     */
    public function upcomingSchedules(): HasMany
    {
        return $this->schedules()
            ->where('start_datetime', '>', now())
            ->orderBy('start_datetime', 'asc');
    }

    /**
     * Get active impediments (currently running).
     */
    public function activeImpediments(): HasMany
    {
        return $this->impediments()
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now());
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

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

    /**
     * Get created at as DateTimeZuluVO.
     */
    public function getCreatedAt(): ?DateTimeZuluVO
    {
        if ($this->created_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->created_at);
    }

    /**
     * Get updated at as DateTimeZuluVO.
     */
    public function getUpdatedAt(): ?DateTimeZuluVO
    {
        if ($this->updated_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->updated_at);
    }

    /**
     * Get deleted at as DateTimeZuluVO.
     */
    public function getDeletedAt(): ?DateTimeZuluVO
    {
        if ($this->deleted_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->deleted_at);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Check if the availability is active on a given date.
     */
    public function isActiveOnDate(DateTimeZuluVO $date): bool
    {
        // Check validity period
        if ($this->validity_start !== null) {
            $start = DateTimeZuluVO::fromCarbon($this->validity_start);
            if ($date->isBefore($start)) {
                return false;
            }
        }

        if ($this->validity_end !== null) {
            $end = DateTimeZuluVO::fromCarbon($this->validity_end);
            if ($date->isAfter($end)) {
                return false;
            }
        }

        // Check day of week
        $dayOfWeek = strtolower($date->format('l'));

        return in_array($dayOfWeek, $this->days ?? [], true);
    }

    /**
     * Check if the availability has any schedules.
     */
    public function hasSchedules(): bool
    {
        return $this->schedules()->exists();
    }

    /**
     * Check if the availability has any impediments.
     */
    public function hasImpediments(): bool
    {
        return $this->impediments()->exists();
    }

    /**
     * Check if the availability is cross-day (daily_start > daily_end).
     * Uses TimeZuluVO comparison.
     */
    public function isCrossDay(): bool
    {
        $start = $this->getDailyStart();
        $end = $this->getDailyEnd();

        if ($start === null || $end === null) {
            return false;
        }

        return $start->isAfter($end);
    }

    /**
     * Check if the availability is on the same day (daily_start <= daily_end).
     */
    public function isSameDay(): bool
    {
        $start = $this->getDailyStart();
        $end = $this->getDailyEnd();

        if ($start === null || $end === null) {
            return true;
        }

        return $start->isBefore($end) || $start->isEqual($end);
    }

    /**
     * Check if the availability has the same start hour as end hour.
     */
    public function isSameHour(): bool
    {
        $start = $this->getDailyStart();
        $end = $this->getDailyEnd();

        if ($start === null || $end === null) {
            return false;
        }

        return $start->isSameHour($end);
    }

    /**
     * Get the duration in minutes.
     */
    public function getDurationInMinutes(): ?int
    {
        $start = $this->getDailyStart();
        $end = $this->getDailyEnd();

        if ($start === null || $end === null) {
            return null;
        }

        return $start->diffInMinutes($end);
    }
}
