<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Contracts\Services;

use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

interface AvailabilityServiceInterface
{
    public function for(Model $schedulable): self;

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function create(AvailabilityRecord $record): Availability;

    /**
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(int|string $id, AvailabilityRecord $record): Availability;

    /**
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws Throwable
     */
    public function delete(int|string $id, bool $force = false): bool;

    public function find(int|string $id): ?Availability;

    /**
     * @throws \RuntimeException
     */
    public function findBySchedulable(?Model $schedulable = null, ?int $limit = null): Collection;

    public function findByType(string $type, ?int $limit = null): Collection;

    public function findActiveAtDate(
        Model $schedulable,
        DateTimeZuluVO $date,
        ?int $limit = null
    ): Collection;

    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null
    ): Collection;

    public function schedulableExists(Model $schedulable): bool;

    public function getSchedulableModel(Model $schedulable): ?string;
}
