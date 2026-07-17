<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
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

    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }

    /**
     * Get start datetime as DateTimeVO.
     */
    public function getStartDatetime(): ?DateTimeVO
    {
        if ($this->start_datetime === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->start_datetime);
    }

    /**
     * Get end datetime as DateTimeVO.
     */
    public function getEndDatetime(): ?DateTimeVO
    {
        if ($this->end_datetime === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->end_datetime);
    }

    /**
     * Get created at as DateTimeVO.
     */
    public function getCreatedAt(): ?DateTimeVO
    {
        if ($this->created_at === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->created_at);
    }

    /**
     * Get updated at as DateTimeVO.
     */
    public function getUpdatedAt(): ?DateTimeVO
    {
        if ($this->updated_at === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->updated_at);
    }

    /**
     * Get deleted at as DateTimeVO.
     */
    public function getDeletedAt(): ?DateTimeVO
    {
        if ($this->deleted_at === null) {
            return null;
        }

        return DateTimeVO::fromCarbon($this->deleted_at);
    }
}
