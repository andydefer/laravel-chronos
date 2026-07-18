<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * Validates that the referenced schedulable entity exists in the database.
 *
 * Ensures that the schedulable_type and schedulable_id reference a valid,
 * existing entity in the system to maintain referential integrity.
 */
final class SchedulableExistsRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that the referenced schedulable entity exists in the database.';
    }

    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->isCreate() || $context->isUpdate();
    }

    /**
     * Validate that the schedulable entity exists.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $schedulableInfo = $this->extractSchedulableInfo($record);

        // Skip if no schedulable info (Impediment or missing data)
        if ($schedulableInfo === null) {
            return null;
        }

        [$schedulableType, $schedulableId] = $schedulableInfo;

        // Validate the class exists
        if (! $this->isClassExists($schedulableType)) {
            return $this->createClassNotExistsError($schedulableType);
        }

        // Validate the entity exists
        if (! $this->isEntityExists($schedulableType, $schedulableId)) {
            return $this->createEntityNotExistsError($schedulableType, $schedulableId);
        }

        return null;
    }

    /**
     * Extract schedulable type and ID from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return array{string, int}|null Array of [type, id] or null if not applicable
     */
    private function extractSchedulableInfo(mixed $record): ?array
    {
        if ($record instanceof AvailabilityRecord) {
            return $this->extractFromAvailability($record);
        }

        if ($record instanceof ScheduleRecord) {
            return $this->extractFromSchedule($record);
        }

        // Impediments don't have schedulable fields directly
        return null;
    }

    /**
     * Extract from AvailabilityRecord.
     *
     * @param  AvailabilityRecord  $record  The record
     * @return array{string, int}|null
     */
    private function extractFromAvailability(AvailabilityRecord $record): ?array
    {
        if ($record->schedulable_type === null || $record->schedulable_id === null) {
            return null;
        }

        return [$record->schedulable_type, $record->schedulable_id];
    }

    /**
     * Extract from ScheduleRecord.
     *
     * @param  ScheduleRecord  $record  The record
     * @return array{string, int}|null
     */
    private function extractFromSchedule(ScheduleRecord $record): ?array
    {
        if ($record->schedulable_type === null || $record->schedulable_id === null) {
            return null;
        }

        return [$record->schedulable_type, $record->schedulable_id];
    }

    /**
     * Check if a class exists.
     *
     * @param  string  $class  The class name to check
     * @return bool True if the class exists
     */
    private function isClassExists(string $class): bool
    {
        return class_exists($class);
    }

    /**
     * Check if an entity exists in the database.
     *
     * @param  string  $type  The entity type class
     * @param  int  $id  The entity ID
     * @return bool True if the entity exists
     */
    private function isEntityExists(string $type, int $id): bool
    {
        return $type::where('id', $id)->exists();
    }

    /**
     * Create an error for non-existent class.
     *
     * @param  string  $schedulableType  The class name
     * @return ValidationErrorRecord The error record
     */
    private function createClassNotExistsError(string $schedulableType): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Schedulable class "%s" does not exist.',
                $schedulableType
            ),
            context: Associative::from([
                'schedulable_type' => $schedulableType,
            ])
        );
    }

    /**
     * Create an error for non-existent entity.
     *
     * @param  string  $schedulableType  The entity type
     * @param  int  $schedulableId  The entity ID
     * @return ValidationErrorRecord The error record
     */
    private function createEntityNotExistsError(
        string $schedulableType,
        int $schedulableId
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Schedulable entity #%d of type "%s" does not exist.',
                $schedulableId,
                $schedulableType
            ),
            context: Associative::from([
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
            ])
        );
    }
}
