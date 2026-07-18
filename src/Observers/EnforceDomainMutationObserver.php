<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Observers;

use AndyDefer\LaravelChronos\Exceptions\ForbiddenModelMutationException;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that enforces domain-controlled mutation rules on models.
 *
 * Prevents any direct model mutations (create, update, delete) unless
 * explicitly permitted by the ChronosMutationContext, ensuring all
 * data modifications flow through the repository layer.
 */
final class EnforceDomainMutationObserver
{
    /**
     * Intercepts model creation attempts.
     *
     * @param  Model  $model  Model being created
     *
     * @throws ForbiddenModelMutationException If mutation not allowed by context
     */
    public function creating(Model $model): void
    {
        $this->guard($model, 'create');
    }

    /**
     * Intercepts model update attempts.
     *
     * @param  Model  $model  Model being updated
     *
     * @throws ForbiddenModelMutationException If mutation not allowed by context
     */
    public function updating(Model $model): void
    {
        $this->guard($model, 'update');
    }

    /**
     * Intercepts model deletion attempts.
     *
     * @param  Model  $model  Model being deleted
     *
     * @throws ForbiddenModelMutationException If mutation not allowed by context
     */
    public function deleting(Model $model): void
    {
        $this->guard($model, 'delete');
    }

    /**
     * Intercepts model restoration attempts.
     *
     * @param  Model  $model  Model being restored
     *
     * @throws ForbiddenModelMutationException If mutation not allowed by context
     */
    public function restoring(Model $model): void
    {
        $this->guard($model, 'restore');
    }

    /**
     * Intercepts force deletion attempts.
     *
     * @param  Model  $model  Model being force deleted
     *
     * @throws ForbiddenModelMutationException If mutation not allowed by context
     */
    public function forceDeleting(Model $model): void
    {
        $this->guard($model, 'forceDelete');
    }

    /**
     * Validates that the current mutation context allows the operation.
     *
     * @param  Model  $model  Model being mutated
     * @param  string  $operation  Operation being performed
     *
     * @throws ForbiddenModelMutationException If mutation context does not permit operation
     */
    private function guard(Model $model, string $operation): void
    {
        if (! ChronosMutationContext::isAllowed()) {
            $contextId = ChronosMutationContext::getContextId();

            if ($contextId) {
                throw ForbiddenModelMutationException::create($model::class, $contextId);
            }

            throw ForbiddenModelMutationException::createWithOperation($model::class, $operation);
        }
    }
}
