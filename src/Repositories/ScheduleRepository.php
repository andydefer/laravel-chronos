<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Repositories\ScheduleRepositoryInterface;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository for Schedule model operations.
 *
 * @extends AbstractChronosRepository<Schedule, ScheduleRecord>
 */
final class ScheduleRepository extends AbstractChronosRepository implements ScheduleRepositoryInterface
{
    /**
     * Initialize repository with Schedule model.
     */
    public function __construct()
    {
        parent::__construct(Schedule::class, ScheduleRecord::class);
    }

    /**
     * {@inheritdoc}
     */
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
            $query->where('status', $filters->status->value);
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
            ->where('status', $status->value);

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
            ->whereRaw('strftime("%w", start_datetime) = ?', [(string) ($dayOfWeek % 7)]);

        if ($availabilityId !== null) {
            $query->where('availability_id', $availabilityId);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findBySchedulable(Model $schedulable): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->get();
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

    /**
     * Find schedules that violate buffer time between consecutive schedules.
     *
     * A violation occurs when the time between the end of one schedule and the
     * start of the next schedule is less than the configured buffer time.
     *
     * @param  int  $availabilityId  The availability ID to check
     * @param  int  $bufferMinutes  Minimum allowed buffer time in minutes
     * @return Collection<int, Schedule> Schedules that violate the buffer time
     */
    public function findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection
    {
        $bufferSeconds = $bufferMinutes * 60;

        $results = DB::table('schedules as s1')
            ->join('schedules as s2', function ($join) {
                $join->on('s1.availability_id', '=', 's2.availability_id')
                    ->whereColumn('s1.id', '!=', 's2.id')
                    ->whereColumn('s1.start_datetime', '<', 's2.start_datetime')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('schedules as s3')
                            ->whereColumn('s3.availability_id', 's1.availability_id')
                            ->whereColumn('s3.id', '!=', 's1.id')
                            ->whereColumn('s3.id', '!=', 's2.id')
                            ->whereColumn('s3.start_datetime', '>', 's1.start_datetime')
                            ->whereColumn('s3.start_datetime', '<', 's2.start_datetime')
                            ->whereNull('s3.deleted_at');
                    });
            })
            ->where('s1.availability_id', $availabilityId)
            ->whereNull('s1.deleted_at')
            ->whereNull('s2.deleted_at')
            ->whereRaw(
                '(strftime("%s", s2.start_datetime) - strftime("%s", s1.end_datetime)) < ?',
                [$bufferSeconds]
            )
            ->select('s1.*')
            ->distinct()
            ->get();

        if ($results->isEmpty()) {
            return new Collection;
        }

        return $this->model->newQuery()
            ->whereIn('id', $results->pluck('id')->all())
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
