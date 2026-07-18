<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\AvailabilityRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
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
use Throwable;

/**
 * Service for managing availability operations.
 *
 * This service implements the business logic for availability management,
 * including validation, authorization, and mutation tracking. All operations
 * are wrapped in context managers for consistent error handling and auditing.
 *
 * @example
 * $service = new AvailabilityService($repository, $validator);
 * $availability = $service->create(new AvailabilityRecord(...));
 *
 * @see AvailabilityServiceInterface
 */
final class AvailabilityService implements AvailabilityServiceInterface
{
    /**
     * @param  AvailabilityRepositoryInterface  $repository  The repository for persistence operations
     * @param  ValidatorInterface  $validator  The validator for business rule validation
     */
    public function __construct(
        private readonly AvailabilityRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function create(AvailabilityRecord $record): Availability
    {
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
     *
     * @throws ModelNotFoundException When the availability does not exist
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function update(int $id, AvailabilityRecord $record): Availability
    {
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
     *
     * @throws ModelNotFoundException When the availability does not exist
     * @throws ValidationException When validation fails and force is false
     * @throws Throwable When the repository operation fails
     */
    public function delete(int $id, bool $force = false): bool
    {
        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $force): bool {
                $existing = $this->findOrFail($id);

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
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): ?Availability => $this->repository->find($id),
            ['operation' => 'find', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findBySchedulable(Model $schedulable): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findBySchedulable($schedulable),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByType(string $type): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findByType($type),
            ['operation' => 'findByType', 'type' => $type]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveAtDate(
        Model $schedulable,
        DateTimeZuluVO $date
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findActiveAtDate(
                $schedulable,
                $date
            ),
            [
                'operation' => 'findActiveAtDate',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'date' => $date->toDateTimeString(),
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findActiveInDateRange(
                $schedulable,
                $start,
                $end
            ),
            [
                'operation' => 'findActiveInDateRange',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
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
     * Finds an availability or throws an exception if not found.
     *
     * @param  int  $id  The availability ID
     * @return Availability The found availability
     *
     * @throws ModelNotFoundException When the availability does not exist
     */
    private function findOrFail(int $id): Availability
    {
        $availability = $this->repository->find($id);

        if ($availability === null) {
            throw ModelNotFoundException::create(Availability::class, $id);
        }

        return $availability;
    }

    /**
     * Validates an operation against business rules.
     *
     * @param  AvailabilityRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation type
     * @param  Availability|null  $existing  The existing availability for update/delete operations
     *
     * @throws ValidationException When validation fails
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
