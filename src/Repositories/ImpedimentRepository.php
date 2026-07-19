<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Repositories\ImpedimentRepositoryInterface;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Apply a limit to the query.
     */
    private function applyLimit(Builder $query, ?int $limit): Builder
    {
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    public function findByAvailability(int $availabilityId, ?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('availability_id', $availabilityId);

        return $this->applyLimit($query, $limit)->get();
    }

    public function findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null, ?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString());

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $this->applyLimit($query, $limit)->get();
    }

    public function findOverlapping(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
        ?int $limit = null,
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

        return $this->applyLimit($query, $limit)->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findBySchedulable(Model $schedulable, ?int $limit = null): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        $query = $this->model->newQuery()
            ->whereHas('availability', function ($query) use ($schedulableType, $schedulableId) {
                $query->where('schedulable_type', $schedulableType)
                    ->where('schedulable_id', $schedulableId);
            });

        return $this->applyLimit($query, $limit)->get();
    }

    public function searchByReason(string $search, ?int $availabilityId = null, ?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('reason', 'like', '%'.$search.'%');

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $this->applyLimit($query, $limit)->get();
    }

    public function findByDate(DateTimeZuluVO $date, ?int $availabilityId = null, ?int $limit = null): Collection
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $query = $this->model->newQuery()
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString());

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $this->applyLimit($query, $limit)->get();
    }

    public function findByAvailabilityInDateRange(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $limit = null,
    ): Collection {
        $query = $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->where('start_datetime', '>=', $start->toDateTimeString())
            ->where('start_datetime', '<=', $end->toDateTimeString());

        return $this->applyLimit($query, $limit)->get();
    }

    public function findActive(?int $availabilityId = null, ?int $limit = null): Collection
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');

        $query = $this->model->newQuery()
            ->where('start_datetime', '<=', $now)
            ->where('end_datetime', '>=', $now);

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $this->applyLimit($query, $limit)->get();
    }

    public function findConflicting(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
        ?int $limit = null,
    ): Collection {
        return $this->findOverlapping($availabilityId, $start, $end, $excludeId, $limit);
    }

    public function findWithInvalidChronology(?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->whereRaw('start_datetime >= end_datetime');

        return $this->applyLimit($query, $limit)->get();
    }

    public function findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes, ?int $limit = null): Collection
    {
        $maxSeconds = $maxDurationMinutes * 60;

        $query = $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->whereRaw('(strftime("%s", end_datetime) - strftime("%s", start_datetime)) > ?', [$maxSeconds]);

        return $this->applyLimit($query, $limit)->get();
    }

    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes, ?int $limit = null): Collection
    {
        $bufferSeconds = $bufferMinutes * 60;

        $results = DB::table('impediments as i1')
            ->join('impediments as i2', function ($join) {
                $join->on('i1.availability_id', '=', 'i2.availability_id')
                    ->whereColumn('i1.id', '!=', 'i2.id')
                    ->whereColumn('i1.start_datetime', '<', 'i2.start_datetime')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('impediments as i3')
                            ->whereColumn('i3.availability_id', 'i1.availability_id')
                            ->whereColumn('i3.id', '!=', 'i1.id')
                            ->whereColumn('i3.id', '!=', 'i2.id')
                            ->whereColumn('i3.start_datetime', '>', 'i1.start_datetime')
                            ->whereColumn('i3.start_datetime', '<', 'i2.start_datetime')
                            ->whereNull('i3.deleted_at');
                    });
            })
            ->where('i1.availability_id', $availabilityId)
            ->whereNull('i1.deleted_at')
            ->whereNull('i2.deleted_at')
            ->whereRaw(
                '(strftime("%s", i2.start_datetime) - strftime("%s", i1.end_datetime)) < ?',
                [$bufferSeconds]
            )
            ->select('i1.*')
            ->distinct();

        if ($limit !== null && $limit > 0) {
            $results->limit($limit);
        }

        $results = $results->get();

        if ($results->isEmpty()) {
            return new Collection;
        }

        return $this->model->newQuery()
            ->whereIn('id', $results->pluck('id')->all())
            ->get();
    }

    public function getBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        $start = $impediment->start_datetime;
        $end = $impediment->end_datetime;

        if ($start === null || $end === null) {
            return new Collection;
        }

        $query = Schedule::where('availability_id', $impediment->availability_id)
            ->where(function ($query) use ($start, $end) {
                $query->where('start_datetime', '<', $end)
                    ->where('end_datetime', '>', $start);
            });

        return $this->applyLimit($query, $limit)->get();
    }

    public function getFullyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        $start = $impediment->start_datetime;
        $end = $impediment->end_datetime;

        if ($start === null || $end === null) {
            return new Collection;
        }

        $query = Schedule::where('availability_id', $impediment->availability_id)
            ->where('start_datetime', '>=', $start)
            ->where('end_datetime', '<=', $end);

        return $this->applyLimit($query, $limit)->get();
    }

    public function getPartiallyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection
    {
        $start = $impediment->start_datetime;
        $end = $impediment->end_datetime;

        if ($start === null || $end === null) {
            return new Collection;
        }

        $query = Schedule::where('availability_id', $impediment->availability_id)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '<', $start)
                        ->where('end_datetime', '>', $start)
                        ->where('end_datetime', '<=', $end);
                })->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_datetime', '>=', $start)
                        ->where('start_datetime', '<', $end)
                        ->where('end_datetime', '>', $end);
                });
            });

        return $this->applyLimit($query, $limit)->get();
    }
}
