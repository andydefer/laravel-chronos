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
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AvailabilityService implements AvailabilityServiceInterface
{
    private ScopedServiceInterface $scope;

    public function __construct(
        private readonly AvailabilityRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {
        $this->scope = new ScopedService;
    }

    public function for(Model $schedulable): self
    {
        $this->scope->for($schedulable);

        return $this;
    }

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

    public function update(int|string $id, AvailabilityRecord $record): Availability
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

    public function delete(int|string $id, bool $force = false): bool
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

    public function find(int|string $id): ?Availability
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            AvailabilityService::class,
            function () use ($id, $schedulable): ?Availability {
                $availability = $this->repository->find($id);

                if ($availability !== null && $schedulable !== null) {
                    if ($availability->schedulable_type !== $schedulable->getMorphClass() ||
                        (string) $availability->schedulable_id !== (string) $schedulable->getKey()) {
                        return null;
                    }
                }

                return $availability;
            },
            ['operation' => 'find', 'id' => $id]
        );
    }

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

    public function findByType(string $type, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            AvailabilityService::class,
            fn (): Collection => $this->repository->findByType($type, $limit),
            ['operation' => 'findByType', 'type' => $type, 'limit' => $limit]
        );
    }

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

    private function findOrFail(int|string $id, ?Model $schedulable = null): Availability
    {
        $availability = $this->repository->find($id);

        if ($availability === null) {
            throw ModelNotFoundException::create(Availability::class, $id);
        }

        if ($schedulable !== null) {
            if ($availability->schedulable_type !== $schedulable->getMorphClass() ||
                (string) $availability->schedulable_id !== (string) $schedulable->getKey()) {
                throw ModelNotFoundException::create(Availability::class, $id);
            }
        }

        return $availability;
    }

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
