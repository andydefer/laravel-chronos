<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Models;

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
}
