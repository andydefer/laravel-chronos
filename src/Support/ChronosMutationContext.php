<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Support;

use Illuminate\Support\Facades\Context;

/**
 * Manages the mutation context for Laravel Chronos.
 *
 * This context ensures that all model mutations (create, update, delete)
 * are only performed through authorized repositories or services.
 */
final class ChronosMutationContext
{
    private const CONTEXT_KEY = 'chronos_mutation_allowed';

    private const CONTEXT_ID_KEY = 'chronos_mutation_id';

    private const CONTEXT_DATA_KEY = 'chronos_mutation_data';

    /**
     * Allow mutations in the current context.
     * Should be called before any repository operation.
     */
    public static function allow(): void
    {
        Context::add(self::CONTEXT_KEY, true);
        self::generateContextId();
    }

    /**
     * Disallow mutations in the current context.
     * Should be called after repository operations.
     */
    public static function disallow(): void
    {
        Context::add(self::CONTEXT_KEY, false);
        Context::forget(self::CONTEXT_ID_KEY);
        Context::forget(self::CONTEXT_DATA_KEY);
    }

    /**
     * Check if mutations are allowed in the current context.
     */
    public static function isAllowed(): bool
    {
        return Context::get(self::CONTEXT_KEY, false) === true;
    }

    /**
     * Generate a unique context ID for tracking.
     */
    private static function generateContextId(): void
    {
        Context::add(self::CONTEXT_ID_KEY, uniqid('chronos_', true));
    }

    /**
     * Get the current context ID.
     */
    public static function getContextId(): ?string
    {
        return Context::get(self::CONTEXT_ID_KEY);
    }

    /**
     * Set additional context data.
     */
    public static function setContextData(array $data): void
    {
        $existing = Context::get(self::CONTEXT_DATA_KEY, []);
        Context::add(self::CONTEXT_DATA_KEY, array_merge($existing, $data));
    }

    /**
     * Get all context data.
     */
    public static function getContextData(): array
    {
        return Context::get(self::CONTEXT_DATA_KEY, []);
    }

    /**
     * Run a callback with mutation context allowed.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withAllowed(callable $callback, array $contextData = []): mixed
    {
        self::allow();

        if (! empty($contextData)) {
            self::setContextData($contextData);
        }

        try {
            return $callback();
        } finally {
            self::disallow();
        }
    }
}
