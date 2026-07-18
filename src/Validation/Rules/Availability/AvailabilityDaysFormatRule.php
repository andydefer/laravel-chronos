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
 * Ensures that:
 * - At least one day is specified
 * - Days are valid WeekDay enum values
 * - No duplicate days exist in the collection
 */
final class AvailabilityDaysFormatRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that days are properly formatted as a WeekDayCollection with no duplicates.';
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
     * Validate the availability days format and integrity.
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

        $days = $record->days;

        // Validate that days are provided
        if ($this->isDaysMissing($days)) {
            return $this->createEmptyDaysError();
        }

        // Validate that days is a collection
        if (! $this->isValidDaysCollection($days)) {
            return $this->createInvalidCollectionError($days);
        }

        // Récupérer les strings de la collection
        $dayStrings = $days->toStrings();
        $allValidDayStrings = $this->getAllValidDayStrings();

        // Vérifier les jours invalides
        $invalidDays = $this->findInvalidDays($dayStrings, $allValidDayStrings);

        // Vérifier les doublons
        $duplicates = $this->findDuplicates($dayStrings);

        // Construire le message d'erreur
        $errors = [];

        if (! empty($invalidDays)) {
            $errors[] = sprintf(
                'Invalid day(s): %s. Allowed days are: %s',
                implode(', ', $invalidDays),
                implode(', ', $allValidDayStrings)
            );
        }

        if (! empty($duplicates)) {
            $errors[] = sprintf(
                'Duplicate day(s) found: %s',
                implode(', ', $duplicates)
            );
        }

        if (! empty($errors)) {
            return new ValidationErrorRecord(
                rule: self::class,
                message: implode(' ', $errors),
                context: Associative::from([
                    'provided_days' => $dayStrings,
                    'invalid_days' => $invalidDays,
                    'duplicates' => $duplicates,
                ])
            );
        }

        return null;
    }

    /**
     * Check if days are missing or empty.
     *
     * @param  mixed  $days  The days collection to check
     * @return bool True if days are null or empty
     */
    private function isDaysMissing(mixed $days): bool
    {
        return $days === null || $days->isEmpty();
    }

    /**
     * Check if the days value is a valid WeekDayCollection.
     *
     * @param  mixed  $days  The days value to check
     * @return bool True if days is a WeekDayCollection
     */
    private function isValidDaysCollection(mixed $days): bool
    {
        return $days instanceof WeekDayCollection;
    }

    /**
     * Find invalid day values.
     *
     * @param  array<string>  $dayStrings  The day strings to check
     * @param  array<string>  $allValidDays  All valid day strings
     * @return array<string> Array of invalid day strings
     */
    private function findInvalidDays(array $dayStrings, array $allValidDays): array
    {
        $invalidDays = [];

        foreach ($dayStrings as $day) {
            if (! in_array($day, $allValidDays, true)) {
                $invalidDays[] = $day;
            }
        }

        return $invalidDays;
    }

    /**
     * Find duplicate day values.
     *
     * @param  array<string>  $dayStrings  The day strings to check
     * @return array<string> Array of duplicate day strings
     */
    private function findDuplicates(array $dayStrings): array
    {
        $uniqueDays = array_unique($dayStrings);

        if (count($uniqueDays) === count($dayStrings)) {
            return [];
        }

        $duplicates = [];
        $seen = [];

        foreach ($dayStrings as $day) {
            if (in_array($day, $seen, true)) {
                if (! in_array($day, $duplicates, true)) {
                    $duplicates[] = $day;
                }
            }
            $seen[] = $day;
        }

        return $duplicates;
    }

    /**
     * Get all valid day strings from the WeekDay enum.
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
     * Create an error for missing days.
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
     * Create an error for invalid collection type.
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
}
