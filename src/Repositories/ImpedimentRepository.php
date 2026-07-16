<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Records\Filters\ImpedimentFiltersRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class ImpedimentRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Impediment::class, ImpedimentRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof ImpedimentFiltersRecord) {
            return;
        }

        if ($filters->availability_id !== null) {
            $query->where('availability_id', $filters->availability_id);
        }

        if ($filters->reason !== null) {
            $query->where('reason', 'like', '%'.$filters->reason.'%');
        }

        if ($filters->start_datetime_from !== null) {
            $query->where('start_datetime', '>=', $filters->start_datetime_from);
        }

        if ($filters->start_datetime_to !== null) {
            $query->where('start_datetime', '<=', $filters->start_datetime_to);
        }

        if ($filters->end_datetime_from !== null) {
            $query->where('end_datetime', '>=', $filters->end_datetime_from);
        }

        if ($filters->end_datetime_to !== null) {
            $query->where('end_datetime', '<=', $filters->end_datetime_to);
        }

        if ($filters->withTrashed === true) {
            $query->withTrashed();
        }
    }
}
