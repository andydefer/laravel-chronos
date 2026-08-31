<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Impediment model representing periods when availability is blocked.
 *
 * @property int $id
 * @property int $availability_id
 * @property string|null $reason
 * @property Carbon $start_datetime Start of the impediment
 * @property Carbon $end_datetime End of the impediment
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * // Computed attributes
 * @property-read bool $is_active Whether the impediment is currently active
 * @property-read bool $is_upcoming Whether the impediment is in the future
 * @property-read bool $is_past Whether the impediment is in the past
 * @property-read bool $is_cross_day Whether the impediment spans across midnight
 * @property-read bool $is_same_day Whether the impediment is within the same day
 * @property-read bool $is_same_hour Whether start and end are at the same hour
 * @property-read int|null $duration_in_minutes Duration in minutes
 *
 * // Relations
 * @property-read Availability $availability
 */
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

    protected $appends = [
        'is_active',
        'is_upcoming',
        'is_past',
        'is_cross_day',
        'is_same_day',
        'is_same_hour',
        'duration_in_minutes',
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
     *
     * @return Attribute<bool, never>
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $now = DateTimeZuluVO::now();
                $start = $this->getStartDatetime();
                $end = $this->getEndDatetime();

                if ($start === null || $end === null) {
                    return false;
                }

                return $now->isBetween($start, $end);
            }
        );
    }

    /**
     * Check if the impediment is upcoming.
     *
     * @return Attribute<bool, never>
     */
    protected function isUpcoming(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $now = DateTimeZuluVO::now();
                $start = $this->getStartDatetime();

                if ($start === null) {
                    return false;
                }

                return $now->isBefore($start);
            }
        );
    }

    /**
     * Check if the impediment is past.
     *
     * @return Attribute<bool, never>
     */
    protected function isPast(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $now = DateTimeZuluVO::now();
                $end = $this->getEndDatetime();

                if ($end === null) {
                    return false;
                }

                return $now->isAfter($end);
            }
        );
    }

    /**
     * Check if the impediment is cross-day (start_date != end_date).
     *
     * @return Attribute<bool, never>
     */
    protected function isCrossDay(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getStartDatetime();
                $end = $this->getEndDatetime();

                if ($start === null || $end === null) {
                    return false;
                }

                return $start->isCrossDay($end);
            }
        );
    }

    /**
     * Check if the impediment is on the same day (start_date == end_date).
     *
     * @return Attribute<bool, never>
     */
    protected function isSameDay(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getStartDatetime();
                $end = $this->getEndDatetime();

                if ($start === null || $end === null) {
                    return true;
                }

                return $start->isSameDay($end);
            }
        );
    }

    /**
     * Check if the impediment has the same hour (start_hour == end_hour).
     *
     * @return Attribute<bool, never>
     */
    protected function isSameHour(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getStartDatetime();
                $end = $this->getEndDatetime();

                if ($start === null || $end === null) {
                    return false;
                }

                return $start->isSameHour($end);
            }
        );
    }

    /**
     * Get the duration in minutes.
     *
     * @return Attribute<int|null, never>
     */
    protected function durationInMinutes(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                $start = $this->getStartDatetime();
                $end = $this->getEndDatetime();

                if ($start === null || $end === null) {
                    return null;
                }

                return (int) $start->diffInMinutes($end);
            }
        );
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
