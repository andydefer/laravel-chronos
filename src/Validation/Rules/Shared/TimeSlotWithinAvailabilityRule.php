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
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

/**
 * Validates that a time slot is within the parent availability's constraints.
 *
 * Ensures that the schedule or impediment's time slot respects:
 * - Validity period of the availability
 * - Daily start and end times
 * - Allowed days of the week
 */
final class TimeSlotWithinAvailabilityRule implements ValidationRule
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
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Validates that a time slot is within the parent availability constraints (validity period, daily bounds, allowed days).';
    }

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
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * Validate that the time slot is within the availability constraints.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract time slot data from the record
        $slotData = $this->extractSlotData($record);

        if ($slotData === null) {
            return null;
        }

        [$availabilityId, $startDatetime, $endDatetime] = $slotData;

        // Find the parent availability
        $availability = Availability::find($availabilityId);

        if ($availability === null) {
            return null;
        }

        // Validate each constraint
        $validityError = $this->validateValidityPeriod($availability, $startDatetime, $endDatetime);
        if ($validityError !== null) {
            return $validityError;
        }

        $dailyBoundsError = $this->validateDailyBounds($availability, $startDatetime, $endDatetime);
        if ($dailyBoundsError !== null) {
            return $dailyBoundsError;
        }

        $daysError = $this->validateDays($availability, $startDatetime);
        if ($daysError !== null) {
            return $daysError;
        }

        return null;
    }

    /**
     * Extract time slot data from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return array{int, DateTimeZuluVO, DateTimeZuluVO}|null Array of [availabilityId, start, end] or null
     */
    private function extractSlotData(mixed $record): ?array
    {
        $availabilityId = null;
        $startDatetime = null;
        $endDatetime = null;

        if ($record instanceof ScheduleRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        } elseif ($record instanceof ImpedimentRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        }

        if ($availabilityId === null || $startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$availabilityId, $startDatetime, $endDatetime];
    }

    /**
     * Validate that the time slot is within the validity period.
     *
     * @param  Availability  $availability  The parent availability
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @return ValidationErrorRecord|null Error if validation fails
     */
    private function validateValidityPeriod(
        Availability $availability,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime
    ): ?ValidationErrorRecord {
        $validityStart = $availability->getValidityStart();
        $validityEnd = $availability->getValidityEnd();

        if (! $this->helper->isWithinValidityPeriod($startDatetime, $validityStart, $validityEnd)) {
            return $this->createValidityPeriodError(
                $startDatetime,
                $endDatetime,
                $validityStart,
                $validityEnd
            );
        }

        return null;
    }

    /**
     * Validate that the time slot is within daily bounds.
     *
     * @param  Availability  $availability  The parent availability
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @return ValidationErrorRecord|null Error if validation fails
     */
    private function validateDailyBounds(
        Availability $availability,
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime
    ): ?ValidationErrorRecord {
        $dailyStart = $availability->getDailyStart();
        $dailyEnd = $availability->getDailyEnd();

        if ($dailyStart === null || $dailyEnd === null) {
            return null;
        }

        $startTime = TimeZuluVO::from($startDatetime->toTimeString());
        $endTime = TimeZuluVO::from($endDatetime->toTimeString());

        if (! $this->helper->isWithinDailyBounds($startDatetime, $dailyStart, $dailyEnd)
            || ! $this->helper->isWithinDailyBounds($endDatetime, $dailyStart, $dailyEnd)) {
            return $this->createDailyBoundsError(
                $startDatetime,
                $endDatetime,
                $dailyStart,
                $dailyEnd
            );
        }

        return null;
    }

    /**
     * Validate that the day of the week is allowed.
     *
     * @param  Availability  $availability  The parent availability
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @return ValidationErrorRecord|null Error if validation fails
     */
    private function validateDays(
        Availability $availability,
        DateTimeZuluVO $startDatetime
    ): ?ValidationErrorRecord {
        $days = $availability->days ?? [];

        if (empty($days)) {
            return null;
        }

        $dayName = strtolower($startDatetime->format('l'));

        if (! in_array($dayName, $days, true)) {
            return $this->createDaysError($dayName, $days);
        }

        return null;
    }

    /**
     * Create an error for validity period violation.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  DateTimeZuluVO|null  $validityStart  The validity start
     * @param  DateTimeZuluVO|null  $validityEnd  The validity end
     * @return ValidationErrorRecord The error record
     */
    private function createValidityPeriodError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Time slot is outside the validity period of the availability (%s to %s).',
                $validityStart?->toDateString() ?? 'null',
                $validityEnd?->toDateString() ?? 'null'
            ),
            context: Associative::from([
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
                'validity_start' => $validityStart?->toDateString(),
                'validity_end' => $validityEnd?->toDateString(),
            ])
        );
    }

    /**
     * Create an error for daily bounds violation.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  TimeZuluVO  $dailyStart  The daily start
     * @param  TimeZuluVO  $dailyEnd  The daily end
     * @return ValidationErrorRecord The error record
     */
    private function createDailyBoundsError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Time slot is outside the daily bounds of the availability (%s to %s).',
                $dailyStart->toTimeString(),
                $dailyEnd->toTimeString()
            ),
            context: Associative::from([
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
                'daily_start' => $dailyStart->toTimeString(),
                'daily_end' => $dailyEnd->toTimeString(),
            ])
        );
    }

    /**
     * Create an error for day not allowed.
     *
     * @param  string  $dayName  The day name
     * @param  array<string>  $allowedDays  The allowed days
     * @return ValidationErrorRecord The error record
     */
    private function createDaysError(string $dayName, array $allowedDays): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Day "%s" is not allowed for this availability. Allowed days: %s',
                $dayName,
                implode(', ', $allowedDays)
            ),
            context: Associative::from([
                'day' => $dayName,
                'allowed_days' => $allowedDays,
            ])
        );
    }
}
