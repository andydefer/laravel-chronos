<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;

/**
 * Validates the format and integrity of days in an availability record.
 *
 * This rule ensures that:
 * - At least one day is specified (not empty)
 * - Days are provided as a WeekDayCollection
 * - All days are valid WeekDay enum values
 * - No duplicate days exist in the collection
 *
 * @example
 * $rule = new AvailabilityDaysFormatRule();
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle validation failure
 * }
 */
final class AvailabilityDaysFormatRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Validates that days are properly formatted as a WeekDayCollection with no duplicates.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule only applies to Availability entity types during create or update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * {@inheritDoc}
     *
     * Validates the days collection format and returns an error if any validation fails.
     *
     * @throws \RuntimeException If the record is not an AvailabilityRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        if (! $record instanceof AvailabilityRecord) {
            return null;
        }

        $days = $record->days;

        $validationError = $this->validateDaysFormat($days);

        if ($validationError !== null) {
            return $validationError;
        }

        return $this->validateDaysContent($days);
    }

    /**
     * Validates the format and type of the days collection.
     *
     * Checks that days are not null/empty and are a proper WeekDayCollection.
     *
     * @param  mixed  $days  The days value to validate
     * @return ValidationErrorRecord|null An error record if format validation fails, null otherwise
     */
    private function validateDaysFormat(mixed $days): ?ValidationErrorRecord
    {
        if ($this->isDaysMissing($days)) {
            return $this->createEmptyDaysError();
        }

        if (! $this->isValidDaysCollection($days)) {
            return $this->createInvalidCollectionError($days);
        }

        return null;
    }

    /**
     * Validates the content of the days collection.
     *
     * Checks that all days are valid enum values and there are no duplicates.
     *
     * @param  WeekDayCollection  $days  The days collection to validate
     * @return ValidationErrorRecord|null An error record if content validation fails, null otherwise
     */
    private function validateDaysContent(WeekDayCollection $days): ?ValidationErrorRecord
    {
        $dayStrings = $days->toStrings();
        $validDayStrings = $this->getAllValidDayStrings();

        $invalidDays = $this->findInvalidDays($dayStrings, $validDayStrings);
        $duplicates = $this->findDuplicates($dayStrings);

        if (empty($invalidDays) && empty($duplicates)) {
            return null;
        }

        return $this->createContentError($dayStrings, $invalidDays, $duplicates);
    }

    /**
     * Checks if days are missing or empty.
     *
     * @param  mixed  $days  The days collection to check
     * @return bool True if days are null or empty
     */
    private function isDaysMissing(mixed $days): bool
    {
        return $days === null || $days->isEmpty();
    }

    /**
     * Checks if the days value is a valid WeekDayCollection.
     *
     * @param  mixed  $days  The days value to check
     * @return bool True if days is a WeekDayCollection
     */
    private function isValidDaysCollection(mixed $days): bool
    {
        return $days instanceof WeekDayCollection;
    }

    /**
     * Finds invalid day values in the collection.
     *
     * @param  array<string>  $dayStrings  The day strings to check
     * @param  array<string>  $validDayStrings  All valid day strings
     * @return array<string> Array of invalid day strings
     */
    private function findInvalidDays(array $dayStrings, array $validDayStrings): array
    {
        $invalidDays = [];

        foreach ($dayStrings as $day) {
            if (! in_array($day, $validDayStrings, true)) {
                $invalidDays[] = $day;
            }
        }

        return $invalidDays;
    }

    /**
     * Finds duplicate day values in the collection.
     *
     * @param  array<string>  $dayStrings  The day strings to check
     * @return array<string> Array of duplicate day strings
     */
    private function findDuplicates(array $dayStrings): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($dayStrings as $day) {
            if (in_array($day, $seen, true) && ! in_array($day, $duplicates, true)) {
                $duplicates[] = $day;
            }
            $seen[] = $day;
        }

        return $duplicates;
    }

    /**
     * Gets all valid day strings from the WeekDay enum.
     *
     * @return array<string> Array of all valid day strings
     */
    private function getAllValidDayStrings(): array
    {
        return array_map(
            fn (WeekDay $day): string => $day->value,
            WeekDay::all()
        );
    }

    /**
     * Creates an error for missing days.
     *
     * @return ValidationErrorRecord The error record
     */
    private function createEmptyDaysError(): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'At least one day must be specified for availability.',
            context: Associative::from([
                'allowed_days' => $this->getAllValidDayStrings(),
            ])
        );
    }

    /**
     * Creates an error for invalid collection type.
     *
     * @param  mixed  $days  The invalid days value
     * @return ValidationErrorRecord The error record
     */
    private function createInvalidCollectionError(mixed $days): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Days must be provided as a WeekDayCollection.',
            context: Associative::from([
                'provided_type' => gettype($days),
            ])
        );
    }

    /**
     * Creates an error for invalid content (invalid days or duplicates).
     *
     * @param  array<string>  $dayStrings  The provided day strings
     * @param  array<string>  $invalidDays  The invalid day strings
     * @param  array<string>  $duplicates  The duplicate day strings
     * @return ValidationErrorRecord The error record
     */
    private function createContentError(
        array $dayStrings,
        array $invalidDays,
        array $duplicates
    ): ValidationErrorRecord {
        $errorMessages = [];

        if (! empty($invalidDays)) {
            $errorMessages[] = sprintf(
                'Invalid day(s): %s. Allowed days are: %s',
                implode(', ', $invalidDays),
                implode(', ', $this->getAllValidDayStrings())
            );
        }

        if (! empty($duplicates)) {
            $errorMessages[] = sprintf(
                'Duplicate day(s) found: %s',
                implode(', ', $duplicates)
            );
        }

        return new ValidationErrorRecord(
            rule: self::class,
            message: implode(' ', $errorMessages),
            context: Associative::from([
                'provided_days' => $dayStrings,
                'invalid_days' => $invalidDays,
                'duplicates' => $duplicates,
            ])
        );
    }
}
