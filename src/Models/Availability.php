<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

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
}
