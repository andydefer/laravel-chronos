<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Repositories\ImpedimentRepositoryInterface;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ImpedimentRepository extends AbstractChronosRepository implements ImpedimentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(Impediment::class, ImpedimentRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof ImpedimentRecord) {
            return;
        }

        if ($filters->availability_id !== null) {
            $query->where('availability_id', $filters->availability_id);
        }

        if ($filters->reason !== null) {
            $query->where('reason', 'like', '%'.$filters->reason.'%');
        }

        if ($filters->start_datetime !== null) {
            $query->where('start_datetime', '>=', $filters->start_datetime->toDateTimeString());
        }

        if ($filters->end_datetime !== null) {
            $query->where('end_datetime', '<=', $filters->end_datetime->toDateTimeString());
        }
    }

    public function findByAvailability(int $availabilityId): Collection
    {
        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->get();
    }

    public function findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString());

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function findOverlapping(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection {
        $query = $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_datetime', '<', $end->toDateTimeString())
                    ->where('end_datetime', '>', $start->toDateTimeString());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Find impediments by schedulable entity.
     * Traverse through the availability relationship.
     */
    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return $this->model->newQuery()
            ->whereHas('availability', function ($query) use ($schedulableType, $schedulableId) {
                $query->where('schedulable_type', $schedulableType)
                    ->where('schedulable_id', $schedulableId);
            })
            ->get();
    }

    public function searchByReason(string $search, ?int $availabilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('reason', 'like', '%'.$search.'%');

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $query = $this->model->newQuery()
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString());

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function findByAvailabilityInDateRange(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
    ): Collection {
        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString())
            ->get();
    }

    public function findActive(?int $availabilityId = null): Collection
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');

        $query = $this->model->newQuery()
            ->where('start_datetime', '<=', $now)
            ->where('end_datetime', '>=', $now);

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function findConflicting(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection {
        return $this->findOverlapping($availabilityId, $start, $end, $excludeId);
    }

    public function findWithInvalidChronology(): Collection
    {
        return $this->model->newQuery()
            ->whereRaw('start_datetime >= end_datetime')
            ->get();
    }

    public function findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection
    {
        $maxSeconds = $maxDurationMinutes * 60;

        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->whereRaw('(strftime("%s", end_datetime) - strftime("%s", start_datetime)) > ?', [$maxSeconds])
            ->get();
    }

    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection
    {
        $bufferSeconds = $bufferMinutes * 60;

        return $this->model->newQuery()
            ->from('impediments as i1')
            ->join('impediments as i2', function ($join) {
                $join->on('i1.availability_id', '=', 'i2.availability_id')
                    ->where('i1.id', '<', 'i2.id');
            })
            ->where('i1.availability_id', $availabilityId)
            ->whereRaw('(strftime("%s", i2.start_datetime) - strftime("%s", i1.end_datetime)) < ?', [$bufferSeconds])
            ->select('i1.*')
            ->get();
    }
}
