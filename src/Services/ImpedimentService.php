<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\ImpedimentRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

final class ImpedimentService implements ImpedimentServiceInterface
{
    public function __construct(
        private readonly ImpedimentRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    public function create(ImpedimentRecord $record): Impediment
    {
        return ServiceContext::within(
            ImpedimentService::class,
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

    public function update(int $id, ImpedimentRecord $record): Impediment
    {
        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($id, $record) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Impediment::class, $id);
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

    public function delete(int $id): bool
    {
        return ServiceContext::within(
            ImpedimentService::class,
            function () use ($id) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Impediment::class, $id);
                }

                $result = $this->validator->validateRecord(
                    new ImpedimentRecord,
                    OperationType::DELETE,
                    $existing
                );

                if ($result->hasErrors()) {
                    throw ValidationException::fromValidationResult($result);
                }

                return ChronosMutationContext::withAllowed(
                    fn () => $this->repository->delete($id)
                );
            },
            ['operation' => 'delete', 'id' => $id]
        );
    }

    public function find(int $id): ?Impediment
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->find($id),
            ['operation' => 'find', 'id' => $id]
        );
    }

    public function findByAvailability(int $availabilityId): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->findByAvailability($availabilityId),
            ['operation' => 'findByAvailability', 'availability_id' => $availabilityId]
        );
    }

    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->findBySchedulable($schedulableType, $schedulableId),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ]
        );
    }

    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->findByDate($date, $availabilityId),
            [
                'operation' => 'findByDate',
                'date' => $date->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->findInDateRange($start, $end, $availabilityId),
            [
                'operation' => 'findInDateRange',
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function findActive(?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->findActive($availabilityId),
            [
                'operation' => 'findActive',
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function searchByReason(string $search, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->searchByReason($search, $availabilityId),
            [
                'operation' => 'searchByReason',
                'search' => $search,
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function isActive(Impediment $impediment): bool
    {
        return $impediment->isActive();
    }

    public function overlapsWith(
        Impediment $impediment,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool {
        return $impediment->overlapsWith($start, $end);
    }

    public function getBlockedSchedules(Impediment $impediment): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->getBlockedSchedules($impediment),
            [
                'operation' => 'getBlockedSchedules',
                'impediment_id' => $impediment->id,
            ]
        );
    }

    public function getFullyBlockedSchedules(Impediment $impediment): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->getFullyBlockedSchedules($impediment),
            [
                'operation' => 'getFullyBlockedSchedules',
                'impediment_id' => $impediment->id,
            ]
        );
    }

    public function getPartiallyBlockedSchedules(Impediment $impediment): Collection
    {
        return ServiceContext::within(
            ImpedimentService::class,
            fn () => $this->repository->getPartiallyBlockedSchedules($impediment),
            [
                'operation' => 'getPartiallyBlockedSchedules',
                'impediment_id' => $impediment->id,
            ]
        );
    }
}
