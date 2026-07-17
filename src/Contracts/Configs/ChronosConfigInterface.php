<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Configs;

/**
 * Interface for Laravel Chronos configuration.
 *
 * Provides a typed interface to the configuration values used by the
 * scheduling system.
 */
interface ChronosConfigInterface
{
    /**
     * Gets the minimum duration for a slot in minutes.
     *
     * @return int The minimum duration in minutes
     */
    public function getMinDuration(): int;

    /**
     * Gets the maximum duration for a slot in minutes.
     *
     * @return int The maximum duration in minutes
     */
    public function getMaxDuration(): int;

    /**
     * Gets the buffer time between bookings in minutes.
     *
     * @return int The buffer time in minutes
     */
    public function getBufferTime(): int;
}
