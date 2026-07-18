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
        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        if ($result->hasErrors()) {
            throw ValidationException::fromValidationResult($result);
        }

        return ChronosMutationContext::withAllowed(
            fn () => $this->repository->create($record)
        );
    }

    public function update(int $id, AvailabilityRecord $record): Availability
    {
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
    }

    public function delete(int $id, bool $force = false): bool
    {
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
    }

    public function find(int $id): ?Availability
    {
        return $this->repository->find($id);
    }

    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return $this->repository->findBySchedulable($schedulableType, $schedulableId);
    }

    public function findByType(string $type): Collection
    {
        return $this->repository->findByType($type);
    }

    public function findActiveAtDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): Collection {
        return $this->repository->findActiveAtDate($schedulableType, $schedulableId, $date);
    }

    public function findActiveInDateRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): Collection {
        return $this->repository->findActiveInDateRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end
        );
    }

    public function schedulableExists(string $schedulableType, int $schedulableId): bool
    {
        return $this->repository->schedulableExists($schedulableType, $schedulableId);
    }

    public function getSchedulableModel(string $schedulableType): ?string
    {
        return $this->repository->getSchedulableModel($schedulableType);
    }
}
