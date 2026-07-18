<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Contracts\Repositories\ScheduleRepositoryInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Support\ServiceContext;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

final class ScheduleService implements ScheduleServiceInterface
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    public function create(ScheduleRecord $record): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
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

    public function update(int $id, ScheduleRecord $record): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id, $record) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Schedule::class, $id);
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
            ScheduleService::class,
            function () use ($id) {
                $existing = $this->find($id);

                if ($existing === null) {
                    throw ModelNotFoundException::create(Schedule::class, $id);
                }

                $result = $this->validator->validateRecord(
                    new ScheduleRecord,
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

    public function find(int $id): ?Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn () => $this->repository->find($id),
            ['operation' => 'find', 'id' => $id]
        );
    }

    public function findByAvailability(int $availabilityId): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn () => $this->repository->findByAvailability($availabilityId),
            ['operation' => 'findByAvailability', 'availability_id' => $availabilityId]
        );
    }

    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn () => $this->repository->findBySchedulable($schedulableType, $schedulableId),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ]
        );
    }

    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn () => $this->repository->findByStatus($status, $availabilityId),
            [
                'operation' => 'findByStatus',
                'status' => $status->value,
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
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
            ScheduleService::class,
            fn () => $this->repository->findInDateRange($start, $end, $availabilityId),
            [
                'operation' => 'findInDateRange',
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function searchByTitle(string $search, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn () => $this->repository->searchByTitle($search, $availabilityId),
            [
                'operation' => 'searchByTitle',
                'search' => $search,
                'availability_id' => $availabilityId,
            ]
        );
    }

    public function cancel(int $id): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id) {
                $schedule = $this->find($id);

                if ($schedule === null) {
                    throw ModelNotFoundException::create(Schedule::class, $id);
                }

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

    public function complete(int $id): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id) {
                $schedule = $this->find($id);

                if ($schedule === null) {
                    throw ModelNotFoundException::create(Schedule::class, $id);
                }

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
        return $schedule->canBeCancelled();
    }

    public function canBeCompleted(Schedule $schedule): bool
    {
        return $schedule->canBeCompleted();
    }
}
