<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Services\ScopedServiceInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Encapsulates the logic for entity scoping.
 *
 * This service manages the scope of a schedulable entity and provides
 * helper methods to inject the entity data into records.
 *
 * @example
 * $scope = new ScopedService();
 * $scope->for($doctor)->injectScopedData($data);
 */
final class ScopedService implements ScopedServiceInterface
{
    private ?Model $schedulable = null;

    /**
     * {@inheritDoc}
     */
    public function for(Model $schedulable): self
    {
        $this->schedulable = $schedulable;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getScopedSchedulable(): ?Model
    {
        return $this->schedulable;
    }

    /**
     * {@inheritDoc}
     */
    public function clearScope(): self
    {
        $this->schedulable = null;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isScoped(): bool
    {
        return $this->schedulable !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function getScopedSchedulableType(): ?string
    {
        return $this->schedulable?->getMorphClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getScopedSchedulableId(): ?int
    {
        return $this->schedulable?->getKey();
    }

    /**
     * {@inheritDoc}
     */
    public function injectScopedData(array $data): array
    {
        if ($this->schedulable === null) {
            return $data;
        }

        $data['schedulable_type'] = $this->schedulable->getMorphClass();
        $data['schedulable_id'] = $this->schedulable->getKey();

        return $data;
    }

    /**
     * Executes a callback with scoping and returns the result.
     *
     * @template T
     *
     * @param  Model  $schedulable  The schedulable entity
     * @param  callable(): T  $callback  The callback to execute
     * @return T The result of the callback
     */
    public function with(Model $schedulable, callable $callback): mixed
    {
        $this->for($schedulable);

        try {
            return $callback();
        } finally {
            $this->clearScope();
        }
    }
}
