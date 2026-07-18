<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Prevents deletion of availabilities that have future bookings.
 *
 * Ensures data integrity by preventing the deletion of an availability
 * that still has scheduled events in the future. This rule only applies
 * to DELETE operations and helps prevent orphaned schedules.
 *
 * @example
 * $rule = new NoFutureBookingsOnDeleteRule();
 * $context = new ValidationContext($record, OperationType::DELETE, $existingEntity);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Cannot delete availability with future bookings
 * }
 */
final class NoFutureBookingsOnDeleteRule implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Prevents deletion of availabilities that have future bookings.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule only applies to DELETE operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && $context->isDelete();
    }

    /**
     * {@inheritDoc}
     *
     * Validates that the availability has no future bookings.
     *
     * @throws \RuntimeException If the existing entity is not an Availability
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $existing = $context->getExistingEntity();

        if (! $existing instanceof Availability) {
            return null;
        }

        $now = DateTimeZuluVO::now();

        if ($this->hasFutureSchedules($existing, $now)) {
            return $this->createFutureBookingsError($existing, $now);
        }

        return null;
    }

    /**
     * Checks if the availability has future schedules.
     *
     * @param  Availability  $availability  The availability to check
     * @param  DateTimeZuluVO  $now  The current timestamp
     * @return bool True if future schedules exist
     */
    private function hasFutureSchedules(
        Availability $availability,
        DateTimeZuluVO $now
    ): bool {
        return $availability->schedules()
            ->where('start_datetime', '>', $now->toDateTimeString())
            ->exists();
    }

    /**
     * Creates an error for future bookings.
     *
     * @param  Availability  $availability  The availability being deleted
     * @param  DateTimeZuluVO  $now  The current timestamp
     * @return ValidationErrorRecord The error record
     */
    private function createFutureBookingsError(
        Availability $availability,
        DateTimeZuluVO $now
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: 'Cannot delete availability that has future bookings.',
            context: Associative::from([
                'availability_id' => $availability->id,
                'now' => $now->toDateTimeString(),
            ])
        );
    }
}
