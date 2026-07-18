<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Validates that specified days exist within the validity period.
 *
 * Ensures that all days selected for an availability are actually present
 * within the date range defined by validity_start and validity_end.
 */
final class DaysWithinValidityPeriodRule implements ValidationRule
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
     * Validate that days exist within the validity period.
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

        // Skip validation if no validity period is defined (perpetual availability)
        if ($this->isPerpetualAvailability($record)) {
            return null;
        }

        // Skip if no days are defined (handled by other rules)
        if ($this->isDaysMissing($record)) {
            return null;
        }

        $invalidDays = $this->findInvalidDays($record);

        if (! empty($invalidDays)) {
            return $this->createInvalidDaysError($invalidDays, $record);
        }

        return null;
    }

    /**
     * Check if the availability is perpetual (no validity dates).
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @return bool True if no validity dates are defined
     */
    private function isPerpetualAvailability(AvailabilityRecord $record): bool
    {
        return $record->validity_start === null
            || $record->validity_end === null;
    }

    /**
     * Check if days are missing from the record.
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @return bool True if days are null or empty
     */
    private function isDaysMissing(AvailabilityRecord $record): bool
    {
        return $record->days === null
            || $record->days->isEmpty();
    }

    /**
     * Find days that are not within the validity period.
     *
     * @param  AvailabilityRecord  $record  The record to check
     * @return array<string> Array of invalid day strings
     */
    private function findInvalidDays(AvailabilityRecord $record): array
    {
        $validDayStrings = $this->getDaysInValidityPeriod(
            $record->validity_start,
            $record->validity_end
        );

        $invalidDays = [];

        foreach ($record->days as $day) {
            if (! in_array($day->value, $validDayStrings, true)) {
                $invalidDays[] = $day->value;
            }
        }

        return $invalidDays;
    }

    /**
     * Get all day names that appear within the validity period.
     *
     * @param  DateTimeZuluVO|null  $start  The validity start date
     * @param  DateTimeZuluVO|null  $end  The validity end date
     * @return array<string> Array of day names in the validity period
     */
    private function getDaysInValidityPeriod(
        ?DateTimeZuluVO $start,
        ?DateTimeZuluVO $end
    ): array {
        if ($start === null || $end === null) {
            return [];
        }

        $days = [];
        $current = $start;

        while ($current->isBefore($end) || $current->isEqual($end)) {
            $dayName = strtolower($current->format('l'));
            if (! in_array($dayName, $days, true)) {
                $days[] = $dayName;
            }
            $current = $current->addDays(1);
        }

        return $days;
    }

    /**
     * Create an error for invalid days outside the validity period.
     *
     * @param  array<string>  $invalidDays  Array of invalid day strings
     * @param  AvailabilityRecord  $record  The record being validated
     * @return ValidationErrorRecord The error record
     */
    private function createInvalidDaysError(
        array $invalidDays,
        AvailabilityRecord $record
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Day(s) %s are not within the validity period (%s to %s).',
                implode(', ', $invalidDays),
                $record->validity_start->toDateString(),
                $record->validity_end->toDateString()
            ),
            context: Associative::from([
                'invalid_days' => $invalidDays,
                'validity_start' => $record->validity_start->toDateString(),
                'validity_end' => $record->validity_end->toDateString(),
                'available_days' => $this->getDaysInValidityPeriod(
                    $record->validity_start,
                    $record->validity_end
                ),
            ])
        );
    }
}
