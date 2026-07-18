<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Exceptions\UnauthorizedRepositoryAccessException;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @template TRecord of AbstractRecord
 *
 * @extends AbstractRepository<TModel, TRecord>
 */
abstract class AbstractChronosRepository extends AbstractRepository
{
    /**
     * Determine if the repository is being called from a service.
     * When enabled (default), throws an exception if called outside a service context.
     */
    private bool $enforceServiceLayer = true;

    /**
     * Disable the service layer enforcement.
     * Use this sparingly for edge cases like test fixtures or data imports.
     */
    public function withoutServiceEnforcement(): self
    {
        $this->enforceServiceLayer = false;

        return $this;
    }

    /**
     * Enable the service layer enforcement (default state).
     */
    public function withServiceEnforcement(): self
    {
        $this->enforceServiceLayer = true;

        return $this;
    }

    /**
     * Asserts that the repository is called from a service.
     *
     * @throws UnauthorizedRepositoryAccessException If called outside a service context and enforcement is enabled
     */
    private function assertCalledFromService(): void
    {
        if (! $this->enforceServiceLayer) {
            return;
        }

        if (! ServiceContext::isInService()) {
            throw UnauthorizedRepositoryAccessException::fromDebugBacktrace(
                $this->modelClass ?? static::class
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Wraps creation in a mutation context and enforces service layer.
     */
    public function create(AbstractRecord $record): Model
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::create($record),
            [
                'operation' => 'create',
                'record_type' => get_class($record),
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps raw creation in a mutation context and enforces service layer.
     */
    public function createRaw(array $data): Model
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::createRaw($data),
            [
                'operation' => 'createRaw',
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps update in a mutation context and enforces service layer.
     */
    public function update(int|string $id, AbstractRecord $record): Model
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::update($id, $record),
            [
                'operation' => 'update',
                'id' => $id,
                'record_type' => get_class($record),
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps raw update in a mutation context and enforces service layer.
     */
    public function updateRaw(int|string $id, array $data): Model
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::updateRaw($id, $data),
            [
                'operation' => 'updateRaw',
                'id' => $id,
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps delete in a mutation context and enforces service layer.
     */
    public function delete(int|string $id): bool
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::delete($id),
            [
                'operation' => 'delete',
                'id' => $id,
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps restore in a mutation context and enforces service layer.
     */
    public function restore(int|string $id): bool
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::restore($id),
            [
                'operation' => 'restore',
                'id' => $id,
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps force delete in a mutation context and enforces service layer.
     */
    public function forceDelete(int|string $id): bool
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::forceDelete($id),
            [
                'operation' => 'forceDelete',
                'id' => $id,
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps bulk delete in a mutation context and enforces service layer.
     */
    public function deleteBulk(AbstractRecord $criteria): int
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::deleteBulk($criteria),
            [
                'operation' => 'deleteBulk',
                'criteria_type' => get_class($criteria),
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps force bulk delete in a mutation context and enforces service layer.
     */
    public function forceDeleteBulk(AbstractRecord $criteria): int
    {
        $this->assertCalledFromService();

        return ChronosMutationContext::withAllowed(
            fn () => parent::forceDeleteBulk($criteria),
            [
                'operation' => 'forceDeleteBulk',
                'criteria_type' => get_class($criteria),
                'service' => ServiceContext::getCurrentService(),
            ]
        );
    }
}
