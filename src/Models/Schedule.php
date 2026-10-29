<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $availability_id
 * @property string $schedulable_type
 * @property string $schedulable_id
 * @property string|null $title
 * @property string|null $description
 * @property Carbon $start_datetime
 * @property Carbon $end_datetime
 * @property ScheduleStatus $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_active
 * @property-read bool $is_upcoming
 * @property-read bool $is_past
 * @property-read bool $is_cross_day
 * @property-read bool $is_same_day
 * @property-read bool $is_same_hour
 * @property-read int|null $duration_in_minutes
 * @property-read bool $can_be_cancelled
 * @property-read bool $can_be_completed
 * @property-read Availability $availability
 * @property-read Model|null $schedulable
 */
final class Schedule extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'schedules';

    protected $fillable = [
        'availability_id',
        'schedulable_type',
        'schedulable_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'status',
        'metadata',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => ScheduleStatus::class,
    ];

    protected $appends = [
        'is_active',
        'is_upcoming',
        'is_past',
        'is_cross_day',
        'is_same_day',
        'is_same_hour',
        'duration_in_minutes',
        'can_be_cancelled',
        'can_be_completed',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function conflictingImpediments()
    {
        return Impediment::where('availability_id', $this->availability_id)
            ->where(function ($query) {
                $query->where('start_datetime', '<', $this->end_datetime)
                    ->where('end_datetime', '>', $this->start_datetime);
            });
    }

    public function overlappingSchedules()
    {
        return self::where('availability_id', $this->availability_id)
            ->where('id', '!=', $this->id)
            ->where(function ($query) {
                $query->where('start_datetime', '<', $this->end_datetime)
                    ->where('end_datetime', '>', $this->start_datetime);
            });
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    public function getStartDatetime(): ?DateTimeZuluVO
    {
        if ($this->start_datetime === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->start_datetime);
    }

    public function getEndDatetime(): ?DateTimeZuluVO
    {
        if ($this->end_datetime === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->end_datetime);
    }

    public function getCreatedAt(): ?DateTimeZuluVO
    {
        if ($this->created_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->created_at);
    }

    public function getUpdatedAt(): ?DateTimeZuluVO
    {
        if ($this->updated_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->updated_at);
    }

    public function getDeletedAt(): ?DateTimeZuluVO
    {
        if ($this->deleted_at === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->deleted_at);
    }

    // ============================================================
    // COMPUTED ATTRIBUTES
    // ============================================================

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

    protected function canBeCancelled(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                return ! in_array($this->status, [
                    ScheduleStatus::CANCELLED,
                    ScheduleStatus::COMPLETED,
                ]);
            }
        );
    }

    protected function canBeCompleted(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                return $this->status === ScheduleStatus::BOOKED
                    && $this->isPast;
            }
        );
    }
}
