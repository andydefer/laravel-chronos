<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for services that support entity scoping.
 *
 * This interface allows services to be scoped to a specific schedulable entity,
 * automatically injecting the entity type and ID into records.
 *
 * @example
 * $service->for($doctor)->create($record);
 * $service->for($doctor)->findBySchedulable();
 *
 * @template T
 */
interface ScopedServiceInterface
{
    /**
     * Sets the schedulable entity context for subsequent operations.
     *
     * @param  Model  $schedulable  The schedulable entity (e.g., Doctor::find(42))
     * @return self Returns the service instance for method chaining
     */
    public function for(Model $schedulable): self;

    /**
     * Gets the currently scoped schedulable entity.
     *
     * @return Model|null The scoped entity or null if none is set
     */
    public function getScopedSchedulable(): ?Model;

    /**
     * Clears the current scope.
     *
     * @return self Returns the service instance for method chaining
     */
    public function clearScope(): self;

    /**
     * Checks if the service is currently scoped to an entity.
     *
     * @return bool True if scoped to an entity
     */
    public function isScoped(): bool;

    /**
     * Gets the schedulable type from the scoped entity.
     *
     * @return string|null The morph class or null if not scoped
     */
    public function getScopedSchedulableType(): ?string;

    /**
     * Gets the schedulable ID from the scoped entity.
     *
     * @return int|null The entity ID or null if not scoped
     */
    public function getScopedSchedulableId(): ?int;

    /**
     * Injects the scoped entity data into an array.
     *
     * @param  array<string, mixed>  $data  The data array to inject into
     * @return array<string, mixed> The modified data array
     */
    public function injectScopedData(array $data): array;
}
