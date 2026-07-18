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
use Throwable;

/**
 * Service for managing schedule operations.
 *
 * This service implements the business logic for schedule management,
 * including creation, validation, status transitions (cancel/complete),
 * and mutation tracking. All operations are wrapped in context managers
 * for consistent error handling and auditing.
 *
 * @example
 * $service = new ScheduleService($repository, $validator);
 * $schedule = $service->create(new ScheduleRecord(...));
 * $cancelled = $service->cancel($schedule->id);
 *
 * @see ScheduleServiceInterface
 */
final class ScheduleService implements ScheduleServiceInterface
{
    /**
     * @param  ScheduleRepositoryInterface  $repository  The repository for persistence operations
     * @param  ValidatorInterface  $validator  The validator for business rule validation
     */
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function create(ScheduleRecord $record): Schedule
    {
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

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the schedule does not exist
     * @throws ValidationException When the record fails validation
     * @throws Throwable When the repository operation fails
     */
    public function update(int $id, ScheduleRecord $record): Schedule
    {
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

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the schedule does not exist
     * @throws ValidationException When validation fails
     * @throws Throwable When the repository operation fails
     */
    public function delete(int $id): bool
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id): bool {
                $existing = $this->findOrFail($id);
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

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): ?Schedule => $this->repository->find($id),
            ['operation' => 'find', 'id' => $id]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByAvailability(int $availabilityId): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByAvailability($availabilityId),
            ['operation' => 'findByAvailability', 'availability_id' => $availabilityId]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findBySchedulable($schedulableType, $schedulableId),
            [
                'operation' => 'findBySchedulable',
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByStatus($status, $availabilityId),
            [
                'operation' => 'findByStatus',
                'status' => $status->value,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findByDate($date, $availabilityId),
            [
                'operation' => 'findByDate',
                'date' => $date->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findInDateRange(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->findInDateRange($start, $end, $availabilityId),
            [
                'operation' => 'findInDateRange',
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function searchByTitle(string $search, ?int $availabilityId = null): Collection
    {
        return ServiceContext::within(
            ScheduleService::class,
            fn (): Collection => $this->repository->searchByTitle($search, $availabilityId),
            [
                'operation' => 'searchByTitle',
                'search' => $search,
                'availability_id' => $availabilityId,
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the schedule does not exist
     * @throws ValidationException When the schedule cannot be cancelled due to business rules
     * @throws Throwable When the update operation fails
     */
    public function cancel(int $id): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id): Schedule {
                $schedule = $this->findOrFail($id);

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

    /**
     * {@inheritDoc}
     *
     * @throws ModelNotFoundException When the schedule does not exist
     * @throws ValidationException When the schedule cannot be completed due to business rules
     * @throws Throwable When the update operation fails
     */
    public function complete(int $id): Schedule
    {
        return ServiceContext::within(
            ScheduleService::class,
            function () use ($id): Schedule {
                $schedule = $this->findOrFail($id);

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

    /**
     * {@inheritDoc}
     *
     * Delegates to the schedule model for business logic.
     */
    public function canBeCancelled(Schedule $schedule): bool
    {
        return $schedule->canBeCancelled();
    }

    /**
     * {@inheritDoc}
     *
     * Delegates to the schedule model for business logic.
     */
    public function canBeCompleted(Schedule $schedule): bool
    {
        return $schedule->canBeCompleted();
    }

    /**
     * Finds a schedule or throws an exception if not found.
     *
     * @param  int  $id  The schedule ID
     * @return Schedule The found schedule
     *
     * @throws ModelNotFoundException When the schedule does not exist
     */
    private function findOrFail(int $id): Schedule
    {
        $schedule = $this->repository->find($id);

        if ($schedule === null) {
            throw ModelNotFoundException::create(Schedule::class, $id);
        }

        return $schedule;
    }

    /**
     * Validates an operation against business rules.
     *
     * @param  ScheduleRecord  $record  The record to validate
     * @param  OperationType  $operation  The operation type
     * @param  Schedule|null  $existing  The existing schedule for update/delete operations
     *
     * @throws ValidationException When validation fails
     */
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
