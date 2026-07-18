<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Configs;

use AndyDefer\LaravelChronos\Enums\EntityType;

/**
 * Interface for Laravel Chronos configuration.
 *
 * Provides a typed interface to the configuration values used by the
 * scheduling system.
 */
interface ChronosConfigInterface
{
    /**
     * Gets the minimum duration for a specific entity type in minutes.
     *
     * @param  EntityType  $entityType  The entity type (AVAILABILITY, SCHEDULE, IMPEDIMENT)
     * @return int The minimum duration in minutes
     */
    public function getMinDuration(EntityType $entityType): int;

    /**
     * Gets the minimum duration for slot search in minutes.
     *
     * This prevents users from searching for slots that are too short,
     * which could generate excessive results and slow down the system.
     *
     * @return int The minimum slot search duration in minutes
     */
    public function getMinSlotSearchDuration(): int;

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
