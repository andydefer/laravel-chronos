<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\ImpedimentRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScopedServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Service for managing impediment operations.
 *
 * This service implements the business logic for impediment management,
 * including validation, authorization, and mutation tracking. All operations
 * are wrapped in context managers for consistent error handling and auditing.
 *
 * @example
 * $service = new ImpedimentService($repository, $validator);
 * $impediment = $service->for($doctor)->create(new ImpedimentRecord(...));
 *
 * @see ImpedimentServiceInterface
 */
final class ImpedimentService implements ImpedimentServiceInterface
{
    private ScopedServiceInterface $scope;

    /**
     * @param  ImpedimentRepositoryInterface  $repository  The repository for persistence operations
     * @param  ValidatorInterface  $validator  The validator for business rule validation
     */
    public function __construct(
        private readonly ImpedimentRepositoryInterface $repository,
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
     *
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function create(ImpedimentRecord $record): Impediment
    {
        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($record): Impediment {
                $this->validateOperation($record, OperationType::CREATE);

                return ChronosMutationContext::withAllowed(
                    fn (): Impediment => $this->repository->create($record)
                );
            },
            ['operation' => 'create']
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the impediment does not exist
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function update(int $id, ImpedimentRecord $record): Impediment
    {
        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($id, $record): Impediment {
                $existing = $this->findOrFail($id);
                $this->validateOperation($record, OperationType::UPDATE, $existing);

                return ChronosMutationContext::withAllowed(
                    fn (): Impediment => $this->repository->update($id, $record)
                );
            },
            ['operation' => 'update', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the impediment does not exist
     * @throws ValidationException When validation fails
     * @throws Throwable When the repository operation fails
     */
    public function delete(int $id): bool
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($id, $schedulable): bool {
                $existing = $this->findOrFail($id, $schedulable);
                $this->validateOperation(
                    new ImpedimentRecord,
                    OperationType::DELETE,
                    $existing
                );

                return ChronosMutationContext::withAllowed(
                    fn (): bool => $this->repository->delete($id)
                );
            },
            ['operation' => 'delete', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Impediment
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($id, $schedulable): ?Impediment {
                $impediment = $this->repository->find($id);

                if ($impediment !== null && $schedulable !== null) {
                    $availability = $impediment->availability;
                    if ($availability === null ||
                        $availability->schedulable_type !== $schedulable->getMorphClass() ||
                        $availability->schedulable_id !== $schedulable->getKey()) {
                        return null;
                    }
                }

                return $impediment;
            },
            ['operation' => 'find', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByAvailability(int $availabilityId, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->findByAvailability($availabilityId, $limit),
            [
                'operation' => 'findByAvailability',
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
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
            ImpedimentService::class,
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
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->findByDate($date, $availabilityId, $limit),
            [
                'operation' => 'findByDate',
                'date' => $date->toDateTimeString(),
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null,
        ?int $limit = null
    ): Collection {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->findInDateRange($start, $end, $availabilityId, $limit),
            [
                'operation' => 'findInDateRange',
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findActive(?int $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->findActive($availabilityId, $limit),
            [
                'operation' => 'findActive',
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function searchByReason(string $search, ?int $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->searchByReason($search, $availabilityId, $limit),
            [
                'operation' => 'searchByReason',
                'search' => $search,
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isActive(Impediment $impediment): bool
    {
        return $impediment->isActive();
    }

    /**
     * {@inheritDoc}
     */
    public function overlapsWith(
        Impediment $impediment,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool {
        return $impediment->overlapsWith($start, $end);
    }

    /**
     * {@inheritDoc}
     */
    public function getBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->getBlockedSchedules($impediment, $limit),
            [
                'operation' => 'getBlockedSchedules',
                'impediment_id' => $impediment->id,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getFullyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->getFullyBlockedSchedules($impediment, $limit),
            [
                'operation' => 'getFullyBlockedSchedules',
                'impediment_id' => $impediment->id,
                'limit' => $limit,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getPartiallyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn (): Collection => $this->repository->getPartiallyBlockedSchedules($impediment, $limit),
            [
                'operation' => 'getPartiallyBlockedSchedules',
                'impediment_id' => $impediment->id,
                'limit' => $limit,
            ]
        );
    }

    /**
     * Finds an impediment or throws an exception if not found.
     *
     * @param  int  $id  The impediment ID
     * @param  Model|null  $schedulable  Optional schedulable entity for ownership verification
     * @return Impediment The found impediment
     *
     * @throws ModelNotFoundException When the impediment does not exist or does not belong to the entity
     */
    private function findOrFail(int $id, ?Model $schedulable = null): Impediment
    {
        $impediment = $this->repository->find($id);

        if ($impediment === null) {
            throw ModelNotFoundException::create(Impediment::class, $id);
        }

        if ($schedulable !== null) {
            $availability = $impediment->availability;
            if ($availability === null ||
                $availability->schedulable_type !== $schedulable->getMorphClass() ||
                $availability->schedulable_id !== $schedulable->getKey()) {
                throw ModelNotFoundException::create(Impediment::class, $id);
            }
        }

        return $impediment;
    }

    /**
     * Validates an operation against business rules.
     *
     * @param  ImpedimentRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation type
     * @param  Impediment|null  $existing  The existing impediment for update/delete operations
     *
     * @throws ValidationException When validation fails
     */
    private function validateOperation(
        ImpedimentRecord $record,
        OperationType $operation,
        ?Impediment $existing = null
    ): void {
        $result = $this->validator->validateRecord($record, $operation, $existing);

        if ($result->hasErrors()) {
            throw ValidationException::fromValidationResult($result);
        }
    }
}
