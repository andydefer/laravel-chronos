<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Support;

/**
 * Context manager for tracking service layer calls.
 *
 * Used to ensure that repositories are only called from services,
 * maintaining proper layer separation in the application.
 */
final class ServiceContext
{
    /**
     * @var array<array{service: string, data: array<string, mixed>}>
     */
    private static array $context = [];

    /**
     * Enter a service context.
     *
     * @param  string  $service  The service class name
     * @param  array<string, mixed>  $data  Optional context data
     */
    public static function enter(string $service, array $data = []): void
    {
        self::$context[] = [
            'service' => $service,
            'data' => $data,
        ];
    }

    /**
     * Exit the current service context.
     */
    public static function exit(): void
    {
        array_pop(self::$context);
    }

    /**
     * Check if we are currently in a service context.
     */
    public static function isInService(): bool
    {
        return ! empty(self::$context);
    }

    /**
     * Get the current service name.
     */
    public static function getCurrentService(): ?string
    {
        return self::$context ? end(self::$context)['service'] : null;
    }

    /**
     * Get the current service context data.
     *
     * @return array<string, mixed>
     */
    public static function getCurrentContextData(): array
    {
        return self::$context ? end(self::$context)['data'] : [];
    }

    /**
     * Get the full service stack.
     *
     * @return array<array{service: string, data: array<string, mixed>}>
     */
    public static function getStack(): array
    {
        return self::$context;
    }

    /**
     * Clear all service context (useful for testing).
     */
    public static function clear(): void
    {
        self::$context = [];
    }

    /**
     * Execute a callback within a service context.
     *
     * @template T
     *
     * @param  string  $service  The service class name
     * @param  callable(): T  $callback  The callback to execute
     * @param  array<string, mixed>  $data  Optional context data
     * @return T The result of the callback
     */
    public static function within(string $service, callable $callback, array $data = []): mixed
    {
        self::enter($service, $data);
        try {
            return $callback();
        } finally {
            self::exit();
        }
    }
}
