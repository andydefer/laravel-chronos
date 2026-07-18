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
 * Validates that the referenced availability exists and belongs to the correct entity.
 *
 * Ensures that when creating a schedule or impediment, the availability exists
 * and is owned by the same schedulable entity.
 */
final class AvailabilityOwnershipValidationRule implements ValidationRule
{
    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && $context->isCreate();
    }

    /**
     * Validate that the availability exists and belongs to the correct entity.
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

        // Find the availability
        $availability = Availability::find($availabilityId);

        if ($availability === null) {
            return $this->createAvailabilityNotFoundError($availabilityId);
        }

        // For impediments, skip the ownership check (they inherit from availability)
        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        // Extract schedulable info from the record
        $schedulableInfo = $this->extractSchedulableInfo($record);

        if ($schedulableInfo === null) {
            return null;
        }

        [$schedulableType, $schedulableId] = $schedulableInfo;

        // Check if the availability belongs to the schedulable entity
        if ($this->isAvailabilityMismatch($availability, $schedulableType, $schedulableId)) {
            return $this->createAvailabilityMismatchError(
                $availabilityId,
                $schedulableType,
                $schedulableId,
                $availability
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
     * Check if the availability does not belong to the schedulable entity.
     *
     * @param  Availability  $availability  The availability
     * @param  string  $schedulableType  The schedulable type
     * @param  int  $schedulableId  The schedulable ID
     * @return bool True if there is a mismatch
     */
    private function isAvailabilityMismatch(
        Availability $availability,
        string $schedulableType,
        int $schedulableId
    ): bool {
        return $availability->schedulable_type !== $schedulableType
            || $availability->schedulable_id !== $schedulableId;
    }

    /**
     * Create an error for missing availability.
     *
     * @param  int  $availabilityId  The availability ID
     * @return ValidationErrorRecord The error record
     */
    private function createAvailabilityNotFoundError(int $availabilityId): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability #%d does not exist.',
                $availabilityId
            ),
            context: Associative::from([
                'availability_id' => $availabilityId,
            ])
        );
    }

    /**
     * Create an error for availability ownership mismatch.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  string  $schedulableType  The schedulable type
     * @param  int  $schedulableId  The schedulable ID
     * @param  Availability  $availability  The availability
     * @return ValidationErrorRecord The error record
     */
    private function createAvailabilityMismatchError(
        int $availabilityId,
        string $schedulableType,
        int $schedulableId,
        Availability $availability
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability #%d does not belong to this schedulable entity (%s#%d).',
                $availabilityId,
                $schedulableType,
                $schedulableId
            ),
            context: Associative::from([
                'availability_id' => $availabilityId,
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'availability_schedulable_type' => $availability->schedulable_type,
                'availability_schedulable_id' => $availability->schedulable_id,
            ])
        );
    }
}
