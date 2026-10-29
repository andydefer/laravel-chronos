<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use AndyDefer\PhpVo\ValueObjects\TimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $type
 * @property string|null $name
 * @property Carbon|null $daily_start
 * @property Carbon|null $daily_end
 * @property array $days
 * @property Carbon|null $validity_start
 * @property Carbon|null $validity_end
 * @property string $schedulable_type
 * @property string $schedulable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $has_schedules
 * @property-read bool $has_impediments
 * @property-read bool $is_cross_day
 * @property-read bool $is_same_day
 * @property-read bool $is_same_hour
 * @property-read int|null $duration_in_minutes
 * @property-read Model|null $schedulable
 * @property-read HasMany<Schedule> $schedules
 * @property-read HasMany<Impediment> $impediments
 * @property-read HasMany<Schedule> $activeSchedules
 * @property-read HasMany<Schedule> $upcomingSchedules
 * @property-read HasMany<Impediment> $activeImpediments
 */
final class Availability extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

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

    protected $appends = [
        'has_schedules',
        'has_impediments',
        'is_cross_day',
        'is_same_day',
        'is_same_hour',
        'duration_in_minutes',
    ];

    protected $with = [
        'schedules',
        'impediments',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function impediments(): HasMany
    {
        return $this->hasMany(Impediment::class);
    }

    public function activeSchedules(): HasMany
    {
        return $this->schedules()
            ->whereIn('status', [
                ScheduleStatus::BOOKED->value,
                ScheduleStatus::AVAILABLE->value,
            ]);
    }

    public function upcomingSchedules(): HasMany
    {
        return $this->schedules()
            ->where('start_datetime', '>', now())
            ->orderBy('start_datetime', 'asc');
    }

    public function activeImpediments(): HasMany
    {
        return $this->impediments()
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now());
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    public function getDailyStart(): ?TimeZuluVO
    {
        if ($this->daily_start === null) {
            return null;
        }

        return TimeZuluVO::from($this->daily_start->format('H:i:s'));
    }

    public function getDailyEnd(): ?TimeZuluVO
    {
        if ($this->daily_end === null) {
            return null;
        }

        return TimeZuluVO::from($this->daily_end->format('H:i:s'));
    }

    public function getValidityStart(): ?DateTimeZuluVO
    {
        if ($this->validity_start === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->validity_start);
    }

    public function getValidityEnd(): ?DateTimeZuluVO
    {
        if ($this->validity_end === null) {
            return null;
        }

        return DateTimeZuluVO::fromCarbon($this->validity_end);
    }

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
    // HELPERS
    // ============================================================

    public function isActiveOnDate(DateTimeZuluVO $date): bool
    {
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

        $dayOfWeek = strtolower($date->format('l'));

        return in_array($dayOfWeek, $this->days ?? [], true);
    }

    protected function hasSchedules(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->schedules()->exists()
        );
    }

    protected function hasImpediments(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->impediments()->exists()
        );
    }

    protected function isCrossDay(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getDailyStart();
                $end = $this->getDailyEnd();

                if ($start === null || $end === null) {
                    return false;
                }

                return $start->isAfter($end);
            }
        );
    }

    protected function isSameDay(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getDailyStart();
                $end = $this->getDailyEnd();

                if ($start === null || $end === null) {
                    return true;
                }

                return $start->isBefore($end) || $start->isEqual($end);
            }
        );
    }

    protected function isSameHour(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $start = $this->getDailyStart();
                $end = $this->getDailyEnd();

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
                $start = $this->getDailyStart();
                $end = $this->getDailyEnd();

                if ($start === null || $end === null) {
                    return null;
                }

                return $start->diffInMinutes($end);
            }
        );
    }
}
