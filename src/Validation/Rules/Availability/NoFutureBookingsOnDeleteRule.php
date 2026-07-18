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
 * that still has scheduled events in the future.
 */
final class NoFutureBookingsOnDeleteRule implements ValidationRule
{
    /**
     * Determine if this rule supports the given validation context.
     *
     * @param  ValidationContext  $context  The validation context to check
     * @return bool True if this rule applies to the context
     */
    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::AVAILABILITY
            && $context->isDelete();
    }

    /**
     * Validate that the availability has no future bookings.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
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
     * Check if the availability has future schedules.
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
     * Create an error for future bookings.
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
