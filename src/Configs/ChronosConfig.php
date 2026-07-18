<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Configs;

use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Enums\EntityType;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration manager for Laravel Chronos.
 *
 * Provides a typed interface to the Laravel configuration repository,
 * ensuring type safety and providing default values for all configuration options.
 */
final class ChronosConfig implements ChronosConfigInterface
{
    private const CONFIG_KEY = 'chronos';

    private const DEFAULT_MIN_DURATION = 15;

    private const DEFAULT_MIN_SLOT_SEARCH = 5;

    private const DEFAULT_MAX_DURATION = 240;

    private const DEFAULT_BUFFER_TIME = 0;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getMinDuration(EntityType $entityType): int
    {
        $key = match ($entityType) {
            EntityType::AVAILABILITY => 'availability',
            EntityType::SCHEDULE => 'schedule',
            EntityType::IMPEDIMENT => 'impediment',
        };

        return (int) $this->config->get(
            self::CONFIG_KEY.'.min_durations.'.$key,
            self::DEFAULT_MIN_DURATION
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMinSlotSearchDuration(): int
    {
        return (int) $this->config->get(
            self::CONFIG_KEY.'.min_durations.slot_search',
            self::DEFAULT_MIN_SLOT_SEARCH
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxDuration(): int
    {
        return (int) $this->config->get(
            self::CONFIG_KEY.'.max_duration',
            self::DEFAULT_MAX_DURATION
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getBufferTime(): int
    {
        return (int) $this->config->get(
            self::CONFIG_KEY.'.buffer_time',
            self::DEFAULT_BUFFER_TIME
        );
    }
}
