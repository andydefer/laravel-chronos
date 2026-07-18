<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * Validates that all required fields are present for an availability record.
 *
 * Ensures that critical fields like name, time slots, and schedulable entity
 * information are provided when creating an availability.
 */
final class AvailabilityRequiredFieldsRule implements ValidationRule
{
    /**
     * List of required field names for availability creation.
     *
     * @var array<string>
     */
    private const REQUIRED_FIELDS = [
        'name',
        'daily_start',
        'daily_end',
        'schedulable_type',
        'schedulable_id',
    ];

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that all required fields (name, daily_start, daily_end, schedulable_type, schedulable_id) are present for availability creation.';
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
            && $context->isCreate();
    }

    /**
     * Validate that all required fields are present.
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

        $missingFields = $this->findMissingFields($record);

        if (! empty($missingFields)) {
            return $this->createMissingFieldsError($missingFields, $record);
        }

        return null;
    }

    /**
     * Find which required fields are missing from the record.
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @return array<string> Array of missing field names
     */
    private function findMissingFields(AvailabilityRecord $record): array
    {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if ($this->isFieldMissing($record, $field)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Check if a specific field is missing from the record.
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @param  string  $field  The field name to check
     * @return bool True if the field is missing
     */
    private function isFieldMissing(AvailabilityRecord $record, string $field): bool
    {
        $value = match ($field) {
            'name' => $record->name,
            'daily_start' => $record->daily_start,
            'daily_end' => $record->daily_end,
            'schedulable_type' => $record->schedulable_type,
            'schedulable_id' => $record->schedulable_id,
            default => null,
        };

        return $value === null;
    }

    /**
     * Create an error for missing required fields.
     *
     * @param  array<string>  $missingFields  Array of missing field names
     * @param  AvailabilityRecord  $record  The record being validated
     * @return ValidationErrorRecord The error record
     */
    private function createMissingFieldsError(
        array $missingFields,
        AvailabilityRecord $record
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'The following fields are required for availability: %s',
                implode(', ', $missingFields)
            ),
            context: Associative::from([
                'missing_fields' => $missingFields,
                'record_type' => get_class($record),
            ])
        );
    }
}
