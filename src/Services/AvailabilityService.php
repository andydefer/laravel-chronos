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
use Illuminate\Support\Collection;

final class AvailabilityService implements AvailabilityServiceInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    public function create(AvailabilityRecord $record): Availability
    {
        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($record) {
                $result = $this->validator->validateRecord($record, OperationType::CREATE);

                if ($result->hasErrors()) {
                    throw ValidationException::fromValidationResult($result);
                }

                return ChronosMutationContext::withAllowed(
                    fn () => $this->repository->create($record)
                );
            },
            ['operation' => 'create']
        );
    }

    public function update(int $id, AvailabilityRecord $record): Availability
    {
        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $record) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Availability::class, $id);
                }

                $result = $this->validator->validateRecord($record, OperationType::UPDATE, $existing);

                if ($result->hasErrors()) {
                    throw ValidationException::fromValidationResult($result);
                }

                return ChronosMutationContext::withAllowed(
                    fn () => $this->repository->update($id, $record)
                );
            },
            ['operation' => 'update', 'id' => $id]
        );
    }

    public function delete(int $id, bool $force = false): bool
    {
        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $force) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Availability::class, $id);
                }

                $result = $this->validator->validateRecord(
                    new AvailabilityRecord,
                    OperationType::DELETE,
                    $existing
                );

                if ($result->hasErrors() && ! $force) {
                    throw ValidationException::fromValidationResult($result);
                }

                return ChronosMutationContext::withAllowed(
                    fn () => $force
                        ? $this->repository->forceDelete($id)
                        : $this->repository->delete($id)
                );
            },
            ['operation' => 'delete', 'id' => $id, 'force' => $force]
        );
    }

    public function find(int $id): ?Availability
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->find($id),
            ['operation' => 'find', 'id' => $id]
        );
    }

    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->findBySchedulable($schedulableType, $schedulableId),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ]
        );
    }

    public function findByType(string $type): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->findByType($type),
            ['operation' => 'findByType', 'type' => $type]
        );
    }

    public function findActiveAtDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->findActiveAtDate($schedulableType, $schedulableId, $date),
            [
                'operation' => 'findActiveAtDate',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'date' => $date->toDateTimeString(),
            ]
        );
    }

    public function findActiveInDateRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->findActiveInDateRange(
                $schedulableType,
                $schedulableId,
                $start,
                $end
            ),
            [
                'operation' => 'findActiveInDateRange',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ]
        );
    }

    public function schedulableExists(string $schedulableType, int $schedulableId): bool
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->schedulableExists($schedulableType, $schedulableId),
            [
                'operation' => 'schedulableExists',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ]
        );
    }

    public function getSchedulableModel(string $schedulableType): ?string
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn () => $this->repository->getSchedulableModel($schedulableType),
            [
                'operation' => 'getSchedulableModel',
                'schedulable_type' => $schedulableType,
            ]
        );
    }
}
