<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\Filters\AvailabilityFiltersRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class AvailabilityRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(Availability::class, AvailabilityRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof AvailabilityFiltersRecord) {
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

        if ($filters->days !== null) {
            $query->whereJsonContains('days', $filters->days);
        }

        if ($filters->validity_start !== null) {
            $query->where('validity_start', '>=', $filters->validity_start);
        }

        if ($filters->validity_end !== null) {
            $query->where('validity_end', '<=', $filters->validity_end);
        }

        if ($filters->withTrashed === true) {
            $query->withTrashed();
        }
    }
}
