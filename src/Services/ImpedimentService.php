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
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

final class ImpedimentService implements ImpedimentServiceInterface
{
    public function __construct(
        private readonly ImpedimentRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function create(ImpedimentRecord $record): Impediment
    {
        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        if ($result->hasErrors()) {
            throw ValidationException::fromValidationResult($result);
        }

        return ChronosMutationContext::withAllowed(
            fn () => $this->repository->create($record)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, ImpedimentRecord $record): Impediment
    {
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
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
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
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Impediment
    {
        return $this->repository->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByAvailability(int $availabilityId): Collection
    {
        return $this->repository->findByAvailability($availabilityId);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return $this->repository->findBySchedulable($schedulableType, $schedulableId);
    }

    /**
     * {@inheritdoc}
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection
    {
        return $this->repository->findByDate($date, $availabilityId);
    }

    /**
     * {@inheritdoc}
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection {
        return $this->repository->findInDateRange($start, $end, $availabilityId);
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(?int $availabilityId = null): Collection
    {
        return $this->repository->findActive($availabilityId);
    }

    /**
     * {@inheritdoc}
     */
    public function searchByReason(string $search, ?int $availabilityId = null): Collection
    {
        return $this->repository->searchByReason($search, $availabilityId);
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(Impediment $impediment): bool
    {
        return $impediment->isActive();
    }

    /**
     * {@inheritdoc}
     */
    public function overlapsWith(
        Impediment $impediment,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end
    ): bool {
        return $impediment->overlapsWith($start, $end);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockedSchedules(Impediment $impediment): Collection
    {
        return $this->repository->getBlockedSchedules($impediment);
    }

    /**
     * {@inheritdoc}
     */
    public function getFullyBlockedSchedules(Impediment $impediment): Collection
    {
        return $this->repository->getFullyBlockedSchedules($impediment);
    }

    /**
     * {@inheritdoc}
     */
    public function getPartiallyBlockedSchedules(Impediment $impediment): Collection
    {
        return $this->repository->getPartiallyBlockedSchedules($impediment);
    }
}
