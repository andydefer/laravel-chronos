<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum Durations
    |--------------------------------------------------------------------------
    |
    | The minimum duration (in minutes) for each entity type.
    | This prevents generating slots that are too short and could slow down
    | the system or cause performance issues.
    |
    | Values:
    | - availability: Minimum duration for availability creation
    | - schedule: Minimum duration for schedule slots
    | - impediment: Minimum duration for impediment slots
    | - slot_search: Minimum duration allowed when searching for slots
    |
    | Default: 15 minutes for all types
    |
    */
    'min_durations' => [
        'availability' => env('CHRONOS_MIN_DURATION_AVAILABILITY', 15),
        'schedule' => env('CHRONOS_MIN_DURATION_SCHEDULE', 15),
        'impediment' => env('CHRONOS_MIN_DURATION_IMPEDIMENT', 15),
        'slot_search' => env('CHRONOS_MIN_DURATION_SLOT_SEARCH', 5),
    ],

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
