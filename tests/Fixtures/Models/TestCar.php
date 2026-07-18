<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Test Car model for polymorphic relationships.
 *
 * Used in testing to verify schedule/availability polymorphic attachments.
 */
final class TestCar extends Model
{
    protected $table = 'test_cars';

    protected $fillable = [
        'model',
        'license_plate',
        'type',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];
}
