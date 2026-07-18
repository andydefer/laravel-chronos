<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a repository is accessed outside a service context.
 */
final class UnauthorizedRepositoryAccessException extends RuntimeException
{
    public function __construct(string $repository, string $caller)
    {
        parent::__construct(
            sprintf(
                'Repository [%s] cannot be accessed directly. It must be called from a Service layer. '.
                'Called from: %s. '.
                'Use ->withoutServiceEnforcement() if you need to bypass this check.',
                class_basename($repository),
                $caller
            )
        );
    }

    /**
     * Create exception with the current stack trace.
     */
    public static function fromDebugBacktrace(string $repository): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? null;

        $callerInfo = $caller
            ? sprintf('%s::%s', $caller['class'] ?? 'unknown', $caller['function'] ?? 'unknown')
            : 'unknown';

        return new self($repository, $callerInfo);
    }
}
