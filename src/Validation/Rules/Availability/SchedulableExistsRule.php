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
 *
 * @example
 * $rule = new SchedulableExistsRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle non-existent schedulable entity
 * }
 */
final class SchedulableExistsRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Validates that the referenced schedulable entity exists in the database.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to create and update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->isCreate() || $context->isUpdate();
    }

    /**
     * {@inheritDoc}
     *
     * Validates that the schedulable entity exists.
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $schedulableInfo = $this->extractSchedulableInfo($record);

        if ($schedulableInfo === null) {
            return null;
        }

        [$schedulableType, $schedulableId] = $schedulableInfo;

        if (! $this->isClassExists($schedulableType)) {
            return $this->createClassNotExistsError($schedulableType);
        }

        if (! $this->isEntityExists($schedulableType, $schedulableId)) {
            return $this->createEntityNotExistsError($schedulableType, $schedulableId);
        }

        return null;
    }

    /**
     * Extracts schedulable type and ID from the record.
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

        return null;
    }

    /**
     * Extracts from AvailabilityRecord.
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
     * Extracts from ScheduleRecord.
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
     * Checks if a class exists.
     *
     * @param  string  $class  The class name to check
     * @return bool True if the class exists
     */
    private function isClassExists(string $class): bool
    {
        return class_exists($class);
    }

    /**
     * Checks if an entity exists in the database.
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
     * Creates an error for non-existent class.
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
     * Creates an error for non-existent entity.
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
