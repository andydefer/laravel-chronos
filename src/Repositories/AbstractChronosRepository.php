<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
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
     * {@inheritDoc}
     *
     * Wraps creation in a mutation context.
     */
    public function create(AbstractRecord $record): Model
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::create($record),
            ['operation' => 'create', 'record_type' => get_class($record)]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps raw creation in a mutation context.
     */
    public function createRaw(array $data): Model
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::createRaw($data),
            ['operation' => 'createRaw']
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps update in a mutation context.
     */
    public function update(int|string $id, AbstractRecord $record): Model
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::update($id, $record),
            [
                'operation' => 'update',
                'id' => $id,
                'record_type' => get_class($record),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps raw update in a mutation context.
     */
    public function updateRaw(int|string $id, array $data): Model
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::updateRaw($id, $data),
            [
                'operation' => 'updateRaw',
                'id' => $id,
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps delete in a mutation context.
     */
    public function delete(int|string $id): bool
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::delete($id),
            [
                'operation' => 'delete',
                'id' => $id,
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps restore in a mutation context.
     */
    public function restore(int|string $id): bool
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::restore($id),
            [
                'operation' => 'restore',
                'id' => $id,
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps force delete in a mutation context.
     */
    public function forceDelete(int|string $id): bool
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::forceDelete($id),
            [
                'operation' => 'forceDelete',
                'id' => $id,
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps bulk delete in a mutation context.
     */
    public function deleteBulk(AbstractRecord $criteria): int
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::deleteBulk($criteria),
            [
                'operation' => 'deleteBulk',
                'criteria_type' => get_class($criteria),
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Wraps force bulk delete in a mutation context.
     */
    public function forceDeleteBulk(AbstractRecord $criteria): int
    {
        return ChronosMutationContext::withAllowed(
            fn () => parent::forceDeleteBulk($criteria),
            [
                'operation' => 'forceDeleteBulk',
                'criteria_type' => get_class($criteria),
            ]
        );
    }
}
