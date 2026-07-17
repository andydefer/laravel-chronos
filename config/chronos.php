<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum Duration
    |--------------------------------------------------------------------------
    |
    | The minimum duration (in minutes) for an availability or schedule slot.
    | Any slot shorter than this will be rejected.
    |
    | Default: 15 minutes
    |
    */
    'min_duration' => env('CHRONOS_MIN_DURATION', 15),

    /*
    |--------------------------------------------------------------------------
    | Maximum Duration
    |--------------------------------------------------------------------------
    |
    | The maximum duration (in minutes) for a schedule slot.
    | Any slot longer than this will be rejected.
    |
    | Default: 240 minutes (4 hours)
    |
    */
    'max_duration' => env('CHRONOS_MAX_DURATION', 240),

    /*
    |--------------------------------------------------------------------------
    | Buffer Time
    |--------------------------------------------------------------------------
    |
    | The buffer time (in minutes) required between two consecutive bookings
    | on the same availability.
    |
    | Default: 0 minutes
    |
    */
    'buffer_time' => env('CHRONOS_BUFFER_TIME', 0),
];
