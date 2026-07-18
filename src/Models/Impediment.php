<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Impediment extends Model
{
    use SoftDeletes;

    protected $table = 'impediments';

    protected $fillable = [
        'availability_id',
        'reason',
        'start_datetime',
        'end_datetime',
        'metadata',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Get the availability that owns this impediment.
     */
    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }

    /**
     * Get the schedules that are blocked by this impediment.
     */
    public function blockedSchedules()
    {
        return Schedule::where('availability_id', $this->availability_id)
            ->where(function ($query) {
                $query->where('start_datetime', '<', $this->end_datetime)
                    ->where('end_datetime', '>', $this->start_datetime);
            });
    }

    /**
     * Get the schedules that are completely within this impediment.
     */
    public function fullyBlockedSchedules()
    {
        return Schedule::where('availability_id', $this->availability_id)
            ->where('start_datetime', '>=', $this->start_datetime)
            ->where('end_datetime', '<=', $this->end_datetime);
    }

    /**
     * Get the schedules that partially overlap with this impediment.
     */
    public function partiallyBlockedSchedules()
    {
        return Schedule::where('availability_id', $this->availability_id)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('start_datetime', '<', $this->start_datetime)
                        ->where('end_datetime', '>', $this->start_datetime)
                        ->where('end_datetime', '<=', $this->end_datetime);
                })->orWhere(function ($q) {
                    $q->where('start_datetime', '>=', $this->start_datetime)
                        ->where('start_datetime', '<', $this->end_datetime)
                        ->where('end_datetime', '>', $this->end_datetime);
                });
            });
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    /**
     * Get start datetime as DateTimeZuluVO.
     */
    public function getStartDatetime(): ?DateTimeZuluVO
    {
        if ($this->start_datetime === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->start_datetime);
    }

    /**
     * Get end datetime as DateTimeZuluVO.
     */
    public function getEndDatetime(): ?DateTimeZuluVO
    {
        if ($this->end_datetime === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->end_datetime);
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
     * Check if the impediment is currently active.
     */
    public function isActive(): bool
    {
        $now = DateTimeZuluVO::now();
        $start = $this->getStartDatetime();
        $end = $this->getEndDatetime();

        if ($start === null || $end === null) {
            return false;
        }

        return $now->isBetween($start, $end);
    }

    /**
     * Check if the impediment is upcoming.
     */
    public function isUpcoming(): bool
    {
        $now = DateTimeZuluVO::now();
        $start = $this->getStartDatetime();

        if ($start === null) {
            return false;
        }

        return $now->isBefore($start);
    }

    /**
     * Check if the impediment is past.
     */
    public function isPast(): bool
    {
        $now = DateTimeZuluVO::now();
        $end = $this->getEndDatetime();

        if ($end === null) {
            return false;
        }

        return $now->isAfter($end);
    }

    /**
     * Check if the impediment is cross-day (start_date != end_date).
     * Uses DateTimeZuluVO comparison.
     */
    public function isCrossDay(): bool
    {
        $start = $this->getStartDatetime();
        $end = $this->getEndDatetime();

        if ($start === null || $end === null) {
            return false;
        }

        return $start->isCrossDay($end);
    }

    /**
     * Check if the impediment is on the same day (start_date == end_date).
     */
    public function isSameDay(): bool
    {
        $start = $this->getStartDatetime();
        $end = $this->getEndDatetime();

        if ($start === null || $end === null) {
            return true;
        }

        return $start->isSameDay($end);
    }

    /**
     * Check if the impediment has the same hour (start_hour == end_hour).
     */
    public function isSameHour(): bool
    {
        $start = $this->getStartDatetime();
        $end = $this->getEndDatetime();

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
        $start = $this->getStartDatetime();
        $end = $this->getEndDatetime();

        if ($start === null || $end === null) {
            return null;
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Check if the impediment overlaps with a given time range.
     */
    public function overlapsWith(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        $impedimentStart = $this->getStartDatetime();
        $impedimentEnd = $this->getEndDatetime();

        if ($impedimentStart === null || $impedimentEnd === null) {
            return false;
        }

        return $start->isBefore($impedimentEnd) && $end->isAfter($impedimentStart);
    }

    /**
     * Check if the impediment fully covers a given time range.
     */
    public function fullyCovers(DateTimeZuluVO $start, DateTimeZuluVO $end): bool
    {
        $impedimentStart = $this->getStartDatetime();
        $impedimentEnd = $this->getEndDatetime();

        if ($impedimentStart === null || $impedimentEnd === null) {
            return false;
        }

        return $start->isAfterOrEqual($impedimentStart) && $end->isBeforeOrEqual($impedimentEnd);
    }
}
