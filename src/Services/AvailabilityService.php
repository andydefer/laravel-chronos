<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\AvailabilityRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScopedServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Service for managing availability operations.
 */
final class AvailabilityService implements AvailabilityServiceInterface
{
    private ScopedServiceInterface $scope;

    /**
     * @param  AvailabilityRepositoryInterface  $repository  The repository for persistence operations
     * @param  ValidatorInterface  $validator  The validator for business rule validation
     */
    public function __construct(
        private readonly AvailabilityRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {
        $this->scope = new ScopedService;
    }

    /**
     * {@inheritDoc}
     */
    public function for(Model $schedulable): self
    {
        $this->scope->for($schedulable);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function create(AvailabilityRecord $record): Availability
    {
        $record = $this->injectScopedDataIntoRecord($record);

        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($record): Availability {
                $this->validateOperation($record, OperationType::CREATE);

                return ChronosMutationContext::withAllowed(
                    fn (): Availability => $this->repository->create($record)
                );
            },
            ['operation' => 'create']
        );
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, AvailabilityRecord $record): Availability
    {
        $record = $this->injectScopedDataIntoRecord($record);

        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $record): Availability {
                $existing = $this->findOrFail($id);
                $this->validateOperation($record, OperationType::UPDATE, $existing);

                return ChronosMutationContext::withAllowed(
                    fn (): Availability => $this->repository->update($id, $record)
                );
            },
            ['operation' => 'update', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id, bool $force = false): bool
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $force, $schedulable): bool {
                $existing = $this->findOrFail($id, $schedulable);

                if (! $force) {
                    $this->validateOperation(
                        new AvailabilityRecord,
                        OperationType::DELETE,
                        $existing
                    );
                }

                return ChronosMutationContext::withAllowed(
                    fn (): bool => $force
                        ? $this->repository->forceDelete($id)
                        : $this->repository->delete($id)
                );
            },
            ['operation' => 'delete', 'id' => $id, 'force' => $force]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Availability
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $schedulable): ?Availability {
                $availability = $this->repository->find($id);

                if ($availability !== null && $schedulable !== null) {
                    if ($availability->schedulable_type !== $schedulable->getMorphClass() ||
                        $availability->schedulable_id !== $schedulable->getKey()) {
                        return null;
                    }
                }

                return $availability;
            },
            ['operation' => 'find', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findBySchedulable(?Model $schedulable = null, ?int $limit = null): Collection
    {
        $schedulable = $schedulable ?? $this->scope->getScopedSchedulable();

        if ($schedulable === null) {
            throw new \RuntimeException(
                'No schedulable entity defined. Use for() or pass a model to findBySchedulable().'
            );
        }

        $this->scope->clearScope();

        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findBySchedulable($schedulable, $limit),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByType(string $type, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findByType($type, $limit),
            ['operation' => 'findByType', 'type' => $type, 'limit' => $limit]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveAtDate(
        Model $schedulable,
        DateTimeZuluVO $date,
        ?int $limit = null
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findActiveAtDate($schedulable, $date, $limit),
            [
                'operation' => 'findActiveAtDate',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'date' => $date->toDateTimeString(),
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findActiveInDateRange($schedulable, $start, $end, null, $limit),
            [
                'operation' => 'findActiveInDateRange',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function schedulableExists(Model $schedulable): bool
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): bool => $this->repository->schedulableExists($schedulable),
            [
                'operation' => 'schedulableExists',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSchedulableModel(Model $schedulable): ?string
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): ?string => $this->repository->getSchedulableModel($schedulable),
            [
                'operation' => 'getSchedulableModel',
                'schedulable_type' => $schedulable->getMorphClass(),
            ]
        );
    }

    /**
     * Injects scoped entity data into the record if scoped.
     */
    private function injectScopedDataIntoRecord(AvailabilityRecord $record): AvailabilityRecord
    {
        if (! $this->scope->isScoped()) {
            return $record;
        }

        $data = $record->toArray();
        $data['schedulable_type'] = $this->scope->getScopedSchedulableType();
        $data['schedulable_id'] = $this->scope->getScopedSchedulableId();

        $this->scope->clearScope();

        return AvailabilityRecord::from($data);
    }

    /**
     * Finds an availability or throws an exception if not found.
     */
    private function findOrFail(int $id, ?Model $schedulable = null): Availability
    {
        $availability = $this->repository->find($id);

        if ($availability === null) {
            throw ModelNotFoundException::create(Availability::class, $id);
        }

        if ($schedulable !== null) {
            if ($availability->schedulable_type !== $schedulable->getMorphClass() ||
                $availability->schedulable_id !== $schedulable->getKey()) {
                throw ModelNotFoundException::create(Availability::class, $id);
            }
        }

        return $availability;
    }

    /**
     * Validates an operation against business rules.
     */
    private function validateOperation(
        AvailabilityRecord $record,
        OperationType $operation,
        ?Availability $existing = null
    ): void {
        $result = $this->validator->validateRecord($record, $operation, $existing);

        if ($result->hasErrors()) {
            throw ValidationException::fromValidationResult($result);
        }
    }
}
