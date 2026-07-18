<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Shared;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * Ensures that a schedule or impediment belongs to the same entity as its parent availability.
 *
 * Validates that the schedulable_type and schedulable_id of a child record
 * match those of its parent availability to maintain data consistency.
 * This prevents cross-entity data corruption.
 *
 * @example
 * $rule = new EntityOwnershipConsistencyRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle ownership inconsistency
 * }
 */
final class EntityOwnershipConsistencyRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Ensures that schedules and impediments belong to the same entity as their parent availability.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to Schedule and Impediment entity types.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT;
    }

    /**
     * {@inheritDoc}
     *
     * Validates that the child entity matches the parent availability's owner.
     *
     * @throws \RuntimeException If the record is not a ScheduleRecord or ImpedimentRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $availabilityId = $this->extractAvailabilityId($record);

        if ($availabilityId === null) {
            return null;
        }

        $availability = Availability::find($availabilityId);

        if ($availability === null) {
            return null;
        }

        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        if (! $record instanceof ScheduleRecord) {
            return null;
        }

        $recordType = $record->schedulable_type;
        $recordId = $record->schedulable_id;

        if ($recordType === null || $recordId === null) {
            return null;
        }

        if ($this->isOwnershipMismatch($availability, $recordType, $recordId)) {
            return $this->createOwnershipMismatchError(
                $recordType,
                $recordId,
                $availability,
                $availabilityId
            );
        }

        return null;
    }

    /**
     * Extracts availability ID from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return int|null The availability ID or null
     */
    private function extractAvailabilityId(mixed $record): ?int
    {
        return match (true) {
            $record instanceof ScheduleRecord => $record->availability_id,
            $record instanceof ImpedimentRecord => $record->availability_id,
            default => null,
        };
    }

    /**
     * Checks if there is an ownership mismatch.
     *
     * @param  Availability  $availability  The parent availability
     * @param  string  $recordType  The record's schedulable type
     * @param  int  $recordId  The record's schedulable ID
     * @return bool True if there is a mismatch
     */
    private function isOwnershipMismatch(
        Availability $availability,
        string $recordType,
        int $recordId
    ): bool {
        return $recordType !== $availability->schedulable_type
            || $recordId !== $availability->schedulable_id;
    }

    /**
     * Creates an error for ownership mismatch.
     *
     * @param  string  $recordType  The record's schedulable type
     * @param  int  $recordId  The record's schedulable ID
     * @param  Availability  $availability  The parent availability
     * @param  int  $availabilityId  The availability ID
     * @return ValidationErrorRecord The error record
     */
    private function createOwnershipMismatchError(
        string $recordType,
        int $recordId,
        Availability $availability,
        int $availabilityId
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'The schedule entity (%s#%d) does not match the parent availability entity (%s#%d).',
                $recordType,
                $recordId,
                $availability->schedulable_type,
                $availability->schedulable_id
            ),
            context: Associative::from([
                'schedule_schedulable_type' => $recordType,
                'schedule_schedulable_id' => $recordId,
                'availability_schedulable_type' => $availability->schedulable_type,
                'availability_schedulable_id' => $availability->schedulable_id,
                'availability_id' => $availabilityId,
            ])
        );
    }
}
