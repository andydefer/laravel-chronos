<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Repositories\ScheduleRepositoryInterface;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ScheduleRepository extends AbstractChronosRepository implements ScheduleRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(Schedule::class, ScheduleRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof ScheduleRecord) {
            return;
        }

        if ($filters->availability_id !== null) {
            $query->where('availability_id', $filters->availability_id);
        }

        if ($filters->schedulable_type !== null && $filters->schedulable_id !== null) {
            $query->where('schedulable_type', $filters->schedulable_type)
                ->where('schedulable_id', $filters->schedulable_id);
        }

        if ($filters->title !== null) {
            $query->where('title', 'like', '%'.$filters->title.'%');
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
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

    public function findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('status', $status);

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function searchByTitle(string $search, ?int $availabilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('title', 'like', '%'.$search.'%');

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

    public function findByDayOfWeek(int $dayOfWeek, ?int $availabilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->whereRaw('DAYOFWEEK(start_datetime) = ?', [$dayOfWeek + 1]);

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    public function findBySchedulable(string $schedulableType, int $schedulableId): Collection
    {
        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->get();
    }

    public function findWithInvalidChronology(): Collection
    {
        return $this->model->newQuery()
            ->where('start_datetime', '>=', 'end_datetime')
            ->get();
    }

    public function findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection
    {
        $maxTime = Carbon::createFromTime(0, $maxDurationMinutes, 0)->format('H:i:s');

        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->whereRaw('TIMEDIFF(end_datetime, start_datetime) > ?', [$maxTime])
            ->get();
    }

    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection
    {
        $bufferTime = Carbon::createFromTime(0, $bufferMinutes, 0)->format('H:i:s');

        return $this->model->newQuery()
            ->from('schedules as s1')
            ->join('schedules as s2', function ($join) {
                $join->on('s1.availability_id', '=', 's2.availability_id')
                    ->where('s1.id', '<', 's2.id');
            })
            ->where('s1.availability_id', $availabilityId)
            ->whereRaw('TIMEDIFF(s2.start_datetime, s1.end_datetime) < ?', [$bufferTime])
            ->select('s1.*')
            ->get();
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

    public function findConflicting(
        int $availabilityId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection {
        return $this->findOverlapping($availabilityId, $start, $end, $excludeId);
    }

    public function hasCrossDaySchedule(int $availabilityId): bool
    {
        return $this->model->newQuery()
            ->where('availability_id', $availabilityId)
            ->whereRaw('DATE(start_datetime) != DATE(end_datetime)')
            ->exists();
    }
}
