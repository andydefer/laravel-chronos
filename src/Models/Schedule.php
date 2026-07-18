<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Schedule extends Model
{
    use SoftDeletes;

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

    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

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
}
