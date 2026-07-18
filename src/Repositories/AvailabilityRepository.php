<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Contracts\Repositories\AvailabilityRepositoryInterface;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AvailabilityRepository extends AbstractChronosRepository implements AvailabilityRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(Availability::class, AvailabilityRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof AvailabilityRecord) {
            return;
        }

        if ($filters->type !== null) {
            $query->where('type', $filters->type);
        }

        if ($filters->name !== null) {
            $query->where('name', 'like', '%'.$filters->name.'%');
        }

        if ($filters->schedulable_type !== null && $filters->schedulable_id !== null) {
            $query->where('schedulable_type', $filters->schedulable_type)
                ->where('schedulable_id', $filters->schedulable_id);
        }

        if ($filters->validity_start !== null) {
            $query->where('validity_start', '>=', $filters->validity_start);
        }

        if ($filters->validity_end !== null) {
            $query->where('validity_end', '<=', $filters->validity_end);
        }

        if ($filters->days !== null && ! $filters->days->isEmpty()) {
            $query->whereJsonContains('days', $filters->days->toStrings());
        }
    }

    public function findBySchedulable(Model $schedulable): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->get();
    }

    public function findByDay(Model $schedulable, WeekDay $day): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->whereJsonContains('days', $day->value)
            ->get();
    }

    public function findOverlapping(
        Model $schedulable,
        WeekDay $day,
        TimeZuluVO $startTime,
        TimeZuluVO $endTime,
        DateTimeZuluVO $validityStart,
        DateTimeZuluVO $validityEnd,
        ?int $excludeId = null,
    ): Collection {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        $query = $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->whereJsonContains('days', $day->value)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($sub) use ($startTime, $endTime) {
                    $sub->where('daily_start', '<', $endTime->toTimeString())
                        ->where('daily_end', '>', $startTime->toTimeString());
                });
            })
            ->where(function ($q) use ($validityStart, $validityEnd) {
                $q->where(function ($sub) use ($validityStart, $validityEnd) {
                    $sub->where('validity_start', '<=', $validityEnd->toDateTimeString())
                        ->where('validity_end', '>=', $validityStart->toDateTimeString());
                });
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function findActiveAtDate(Model $schedulable, DateTimeZuluVO $date): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->where(function ($q) use ($date) {
                $q->where('validity_start', '<=', $date->toDateTimeString())
                    ->orWhereNull('validity_start');
            })
            ->where(function ($q) use ($date) {
                $q->where('validity_end', '>=', $date->toDateTimeString())
                    ->orWhereNull('validity_end');
            })
            ->get();
    }

    public function findActiveInDateRange(
        Model $schedulable,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $excludeId = null,
    ): Collection {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        $query = $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($sub) use ($start, $end) {
                    $sub->where('validity_start', '<=', $end->toDateTimeString())
                        ->where('validity_end', '>=', $start->toDateTimeString());
                });
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function findCrossDayAvailabilities(Model $schedulable): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->whereRaw('daily_start > daily_end')
            ->get();
    }

    public function findShortDurations(Model $schedulable, int $minMinutes): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();
        $minSeconds = $minMinutes * 60;

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->whereRaw('(strftime("%s", daily_end) - strftime("%s", daily_start)) < ?', [$minSeconds])
            ->get();
    }

    public function findInvalidDateRanges(Model $schedulable): Collection
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        return $this->model->newQuery()
            ->where('schedulable_type', $schedulableType)
            ->where('schedulable_id', $schedulableId)
            ->where(function ($q) {
                $q->whereRaw('daily_start >= daily_end')
                    ->orWhereRaw('validity_start >= validity_end')
                    ->orWhereNull('validity_start')
                    ->orWhereNull('validity_end');
            })
            ->get();
    }

    public function findWithFutureSchedules(int $availabilityId, DateTimeZuluVO $now): bool
    {
        return $this->model->newQuery()
            ->where('id', $availabilityId)
            ->whereHas('schedules', function ($q) use ($now) {
                $q->where('start_datetime', '>', $now->toDateTimeString());
            })
            ->exists();
    }

    public function findByType(string $type): Collection
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->get();
    }

    public function schedulableExists(Model $schedulable): bool
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        if (! class_exists($schedulableType)) {
            return false;
        }

        return $schedulableType::where('id', $schedulableId)->exists();
    }

    public function getSchedulableModel(Model $schedulable): ?string
    {
        $schedulableType = $schedulable->getMorphClass();
        $schedulableId = (int) $schedulable->getKey();

        if (! class_exists($schedulableType)) {
            return null;
        }

        if (! $schedulableType::where('id', $schedulableId)->exists()) {
            return null;
        }

        return $schedulableType;
    }
}
