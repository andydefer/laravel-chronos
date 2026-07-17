<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\Filters\ScheduleFiltersRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class ScheduleRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Schedule::class, ScheduleRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof ScheduleFiltersRecord) {
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

        if ($filters->start_datetime_from !== null) {
            $query->where('start_datetime', '>=', $filters->start_datetime_from->toDateTimeString());
        }

        if ($filters->start_datetime_to !== null) {
            $query->where('start_datetime', '<=', $filters->start_datetime_to->toDateTimeString());
        }

        if ($filters->end_datetime_from !== null) {
            $query->where('end_datetime', '>=', $filters->end_datetime_from->toDateTimeString());
        }

        if ($filters->end_datetime_to !== null) {
            $query->where('end_datetime', '<=', $filters->end_datetime_to->toDateTimeString());
        }

        if ($filters->withTrashed === true) {
            $query->withTrashed();
        }
    }
}
