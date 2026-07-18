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
 */
final class EntityOwnershipConsistencyRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Ensures that schedules and impediments belong to the same entity as their parent availability.';
    }

    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT;
    }

    /**
     * Validate that the child entity matches the parent availability's owner.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract availability ID from the record
        $availabilityId = $this->extractAvailabilityId($record);

        if ($availabilityId === null) {
            return null;
        }

        // Find the parent availability
        $availability = Availability::find($availabilityId);

        if ($availability === null) {
            return null;
        }

        // Skip validation for Impediment (they inherit from availability)
        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        // Extract schedulable info from the record
        $schedulableInfo = $this->extractSchedulableInfo($record);

        if ($schedulableInfo === null) {
            return null;
        }

        [$recordType, $recordId] = $schedulableInfo;

        // Check if the record's schedulable matches the availability's schedulable
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
     * Extract availability ID from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return int|null The availability ID or null
     */
    private function extractAvailabilityId(mixed $record): ?int
    {
        if ($record instanceof ScheduleRecord) {
            return $record->availability_id;
        }

        if ($record instanceof ImpedimentRecord) {
            return $record->availability_id;
        }

        return null;
    }

    /**
     * Extract schedulable type and ID from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return array{string, int}|null Array of [type, id] or null
     */
    private function extractSchedulableInfo(mixed $record): ?array
    {
        if (! $record instanceof ScheduleRecord) {
            return null;
        }

        if ($record->schedulable_type === null || $record->schedulable_id === null) {
            return null;
        }

        return [$record->schedulable_type, $record->schedulable_id];
    }

    /**
     * Check if there is an ownership mismatch.
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
     * Create an error for ownership mismatch.
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
