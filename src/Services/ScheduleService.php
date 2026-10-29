<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\ScheduleRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScopedServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ScheduleService implements ScheduleServiceInterface
{
    private ScopedServiceInterface $scope;

    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {
        $this->scope = new ScopedService;
    }

    public function for(Model $schedulable): self
    {
        $this->scope->for($schedulable);

        return $this;
    }

    public function create(ScheduleRecord $record): Schedule
    {
        $record = $this->injectScopedDataIntoRecord($record);

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($record): Schedule {
                $this->validateOperation($record, OperationType::CREATE);

                return ChronosMutationContext::withAllowed(
                    fn (): Schedule => $this->repository->create($record)
                );
            },
            ['operation' => 'create']
        );
    }

    public function update(int|string $id, ScheduleRecord $record): Schedule
    {
        $record = $this->injectScopedDataIntoRecord($record);

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $record): Schedule {
                $existing = $this->findOrFail($id);
                $this->validateOperation($record, OperationType::UPDATE, $existing);

                return ChronosMutationContext::withAllowed(
                    fn (): Schedule => $this->repository->update($id, $record)
                );
            },
            ['operation' => 'update', 'id' => $id]
        );
    }

    public function delete(int|string $id): bool
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $schedulable): bool {
                $existing = $this->findOrFail($id, $schedulable);
                $this->validateOperation(
                    new ScheduleRecord,
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

    public function find(int|string $id): ?Schedule
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $schedulable): ?Schedule {
                $schedule = $this->repository->find($id);

                if ($schedule !== null && $schedulable !== null) {
                    if ($schedule->schedulable_type !== $schedulable->getMorphClass() ||
                        $schedule->schedulable_id !== (string) $schedulable->getKey()) {
                        return null;
                    }
                }

                return $schedule;
            },
            ['operation' => 'find', 'id' => $id]
        );
    }

    public function findByAvailability(int|string $availabilityId, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByAvailability($availabilityId, $limit),
            [
                'operation' => 'findByAvailability',
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
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
            ScheduleService::class,
            fn (): Collection => $this->repository->findBySchedulable($schedulable, $limit),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulable->getMorphClass(),
                'schedulable_id' => $schedulable->getKey(),
                'limit' => $limit,
            ]
        );
    }

    public function findByStatus(ScheduleStatus $status, int|string|null $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByStatus($status, $availabilityId, $limit),
            [
                'operation' => 'findByStatus',
                'status' => $status->value,
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    public function findByDate(DateTimeZuluVO $date, int|string|null $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByDate($date, $availabilityId, $limit),
            [
                'operation' => 'findByDate',
                'date' => $date->toDateTimeString(),
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int|string|null $availabilityId = null,
        ?int $limit = null
    ): Collection {
        return ServiceContext::within(
            ScheduleService::class,
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

    public function searchByTitle(string $search, int|string|null $availabilityId = null, ?int $limit = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->searchByTitle($search, $availabilityId, $limit),
            [
                'operation' => 'searchByTitle',
                'search' => $search,
                'availability_id' => $availabilityId,
                'limit' => $limit,
            ]
        );
    }

    public function cancel(int|string $id): Schedule
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $schedulable): Schedule {
                $schedule = $this->findOrFail($id, $schedulable);

                if (! $this->canBeCancelled($schedule)) {
                    throw new ValidationException(
                        ['Schedule cannot be cancelled. Current status: '.$schedule->status->value],
                        'Schedule cannot be cancelled.'
                    );
                }

                $record = ScheduleRecord::from([
                    'status' => ScheduleStatus::CANCELLED,
                ]);

                return $this->update($id, $record);
            },
            ['operation' => 'cancel', 'id' => $id]
        );
    }

    public function complete(int|string $id): Schedule
    {
        $schedulable = $this->scope->getScopedSchedulable();
        $this->scope->clearScope();

        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $schedulable): Schedule {
                $schedule = $this->findOrFail($id, $schedulable);

                if (! $this->canBeCompleted($schedule)) {
                    throw new ValidationException(
                        ['Schedule cannot be completed. Current status: '.$schedule->status->value],
                        'Schedule cannot be completed.'
                    );
                }

                $record = ScheduleRecord::from([
                    'status' => ScheduleStatus::COMPLETED,
                ]);

                return $this->update($id, $record);
            },
            ['operation' => 'complete', 'id' => $id]
        );
    }

    public function canBeCancelled(Schedule $schedule): bool
    {
        return $schedule->can_be_cancelled;
    }

    public function canBeCompleted(Schedule $schedule): bool
    {
        return $schedule->can_be_completed;
    }

    private function injectScopedDataIntoRecord(ScheduleRecord $record): ScheduleRecord
    {
        if (! $this->scope->isScoped()) {
            return $record;
        }

        $data = $record->toArray();
        $data['schedulable_type'] = $this->scope->getScopedSchedulableType();
        $data['schedulable_id'] = $this->scope->getScopedSchedulableId();

        $this->scope->clearScope();

        return ScheduleRecord::from($data);
    }

    private function findOrFail(int|string $id, ?Model $schedulable = null): Schedule
    {
        $schedule = $this->repository->find($id);

        if ($schedule === null) {
            throw ModelNotFoundException::create(Schedule::class, $id);
        }

        if ($schedulable !== null) {
            if ($schedule->schedulable_type !== $schedulable->getMorphClass() ||
                $schedule->schedulable_id !== (string) $schedulable->getKey()) {
                throw ModelNotFoundException::create(Schedule::class, $id);
            }
        }

        return $schedule;
    }

    private function validateOperation(
        ScheduleRecord $record,
        OperationType $operation,
        ?Schedule $existing = null
    ): void {
        $result = $this->validator->validateRecord($record, $operation, $existing);

        if ($result->hasErrors()) {
            throw ValidationException::fromValidationResult($result);
        }
    }
}
