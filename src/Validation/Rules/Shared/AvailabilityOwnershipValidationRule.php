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
 * and is owned by the same schedulable entity. This maintains referential
 * integrity between child records and their parent availability.
 *
 * @example
 * $rule = new AvailabilityOwnershipValidationRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle ownership validation failure
 * }
 */
final class AvailabilityOwnershipValidationRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Validates that the referenced availability exists and belongs to the correct schedulable entity.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to Schedule and Impediment entity types during create operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && $context->isCreate();
    }

    /**
     * {@inheritDoc}
     *
     * Validates that the availability exists and belongs to the correct entity.
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
            return $this->createAvailabilityNotFoundError($availabilityId);
        }

        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        if (! $record instanceof ScheduleRecord) {
            return null;
        }

        $schedulableType = $record->schedulable_type;
        $schedulableId = $record->schedulable_id;

        if ($schedulableType === null || $schedulableId === null) {
            return null;
        }

        if ($this->isOwnershipMismatch($availability, $schedulableType, $schedulableId)) {
            return $this->createOwnershipMismatchError(
                $availabilityId,
                $schedulableType,
                $schedulableId,
                $availability
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
     * Checks if the availability does not belong to the schedulable entity.
     *
     * @param  Availability  $availability  The availability
     * @param  string  $schedulableType  The schedulable type
     * @param  int  $schedulableId  The schedulable ID
     * @return bool True if there is a mismatch
     */
    private function isOwnershipMismatch(
        Availability $availability,
        string $schedulableType,
        int $schedulableId
    ): bool {
        return $availability->schedulable_type !== $schedulableType
            || $availability->schedulable_id !== $schedulableId;
    }

    /**
     * Creates an error for missing availability.
     *
     * @param  int  $availabilityId  The availability ID
     * @return ValidationErrorRecord The error record
     */
    private function createAvailabilityNotFoundError(int $availabilityId): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf('Availability #%d does not exist.', $availabilityId),
            context: Associative::from([
                'availability_id' => $availabilityId,
            ])
        );
    }

    /**
     * Creates an error for availability ownership mismatch.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  string  $schedulableType  The schedulable type
     * @param  int  $schedulableId  The schedulable ID
     * @param  Availability  $availability  The availability
     * @return ValidationErrorRecord The error record
     */
    private function createOwnershipMismatchError(
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
