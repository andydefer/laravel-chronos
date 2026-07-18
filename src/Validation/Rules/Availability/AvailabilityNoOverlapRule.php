<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prevents overlapping availabilities for the same schedulable entity.
 *
 * Ensures that an entity cannot have two availabilities that overlap in:
 * - Days of the week
 * - Time slots (daily_start to daily_end)
 * - Validity periods
 */
final class AvailabilityNoOverlapRule implements ValidationRule
{
    /**
     * Constructor with dependency injection.
     *
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     */
    public function __construct(
        private readonly ValidationHelperService $helper
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Prevents overlapping availabilities for the same schedulable entity.';
    }

    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * Validate that the availability does not overlap with existing ones.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        if (! $record instanceof AvailabilityRecord) {
            return null;
        }

        // Skip if required data is missing (handled by other rules)
        if ($this->isRequiredDataMissing($record)) {
            return null;
        }

        $conflictingAvailability = $this->findConflictingAvailability($record, $context);

        if ($conflictingAvailability !== null) {
            return $this->createOverlapError($conflictingAvailability, $record);
        }

        return null;
    }

    /**
     * Check if required data for overlap validation is missing.
     *
     * @param  AvailabilityRecord  $record  The availability record
     * @return bool True if required data is missing
     */
    private function isRequiredDataMissing(AvailabilityRecord $record): bool
    {
        return $record->schedulable_type === null
            || $record->schedulable_id === null
            || $record->daily_start === null
            || $record->daily_end === null
            || $record->days === null
            || $record->days->isEmpty();
    }

    /**
     * Find a conflicting availability in the database.
     *
     * @param  AvailabilityRecord  $record  The availability record to check
     * @param  ValidationContext  $context  The validation context
     * @return Availability|null The conflicting availability or null
     */
    private function findConflictingAvailability(
        AvailabilityRecord $record,
        ValidationContext $context
    ): ?Availability {
        $query = $this->buildOverlapQuery($record);
        $this->excludeCurrentEntity($query, $context);

        return $query->first();
    }

    /**
     * Build the query to find overlapping availabilities.
     *
     * @param  AvailabilityRecord  $record  The availability record
     * @return Builder The query builder
     */
    private function buildOverlapQuery(AvailabilityRecord $record): Builder
    {
        return Availability::where('schedulable_type', $record->schedulable_type)
            ->where('schedulable_id', $record->schedulable_id)
            ->where(function ($query) use ($record) {
                $this->addDayCondition($query, $record->days);
                $this->addTimeCondition($query, $record->daily_start, $record->daily_end);
                $this->addValidityPeriodCondition($query, $record->validity_start, $record->validity_end);
            });
    }

    /**
     * Add day overlap condition to the query.
     *
     * @param  Builder  $query  The query builder
     * @param  WeekDayCollection  $days  The days to check
     */
    private function addDayCondition(Builder $query, WeekDayCollection $days): void
    {
        $dayStrings = $days->toStrings();
        $query->whereJsonContains('days', $dayStrings);
    }

    /**
     * Add time overlap condition to the query.
     *
     * @param  Builder  $query  The query builder
     * @param  TimeZuluVO  $dailyStart  The daily start time
     * @param  TimeZuluVO  $dailyEnd  The daily end time
     */
    private function addTimeCondition(
        Builder $query,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd
    ): void {
        $query->where(function ($subQuery) use ($dailyStart, $dailyEnd) {
            $subQuery->where('daily_start', '<', $dailyEnd->toTimeString())
                ->where('daily_end', '>', $dailyStart->toTimeString());
        });
    }

    /**
     * Add validity period overlap condition to the query.
     *
     * @param  Builder  $query  The query builder
     * @param  DateTimeZuluVO|null  $validityStart  The validity start date
     * @param  DateTimeZuluVO|null  $validityEnd  The validity end date
     */
    private function addValidityPeriodCondition(
        Builder $query,
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): void {
        if ($validityStart !== null && $validityEnd !== null) {
            $query->where(function ($subQuery) use ($validityStart, $validityEnd) {
                $subQuery->where('validity_start', '<=', $validityEnd->toDateTimeString())
                    ->where('validity_end', '>=', $validityStart->toDateTimeString());
            });
        }
    }

    /**
     * Exclude the current entity being updated from the query.
     *
     * @param  Builder  $query  The query builder
     * @param  ValidationContext  $context  The validation context
     */
    private function excludeCurrentEntity(
        Builder $query,
        ValidationContext $context
    ): void {
        $existingId = $context->hasExistingEntity()
            ? $context->getExistingEntity()?->id
            : null;

        if ($existingId !== null) {
            $query->where('id', '!=', $existingId);
        }
    }

    /**
     * Create an error for overlapping availability.
     *
     * @param  Availability  $conflicting  The conflicting availability
     * @param  AvailabilityRecord  $record  The record being validated
     * @return ValidationErrorRecord The error record
     */
    private function createOverlapError(
        Availability $conflicting,
        AvailabilityRecord $record
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability overlaps with existing availability #%d for the same schedulable entity.',
                $conflicting->id
            ),
            context: Associative::from([
                'conflicting_availability_id' => $conflicting->id,
                'schedulable_type' => $record->schedulable_type,
                'schedulable_id' => $record->schedulable_id,
            ])
        );
    }
}
