<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Shared;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Enforces buffer time between consecutive events on the same availability.
 *
 * Ensures that there is a minimum gap between the end of one event and
 * the start of the next event, preventing back-to-back bookings and
 * allowing for preparation or cleanup time.
 *
 * @example
 * $rule = new BufferTimeRule($helper, $config);
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle buffer time violation
 * }
 */
final class BufferTimeRule implements ValidationRule
{
    /**
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     * @param  ChronosConfigInterface  $config  Configuration containing buffer time
     */
    public function __construct(
        private readonly ValidationHelperService $helper,
        private readonly ChronosConfigInterface $config
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Enforces buffer time between consecutive events on the same availability.';
    }

    /**
     * {@inheritDoc}
     *
     * This rule applies to Schedule and Impediment entity types during create or update operations.
     */
    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && ($context->isCreate() || $context->isUpdate());
    }

    /**
     * {@inheritDoc}
     *
     * Validates that buffer time is respected.
     *
     * @throws \RuntimeException If the record is not a ScheduleRecord or ImpedimentRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $bufferData = $this->extractBufferData($record, $context);

        if ($bufferData === null) {
            return null;
        }

        [$availabilityId, $startDatetime, $endDatetime, $excludeId] = $bufferData;

        $bufferMinutes = $this->config->getBufferTime();

        if ($bufferMinutes <= 0) {
            return null;
        }

        $scheduleError = $this->validatePreviousSchedule(
            $availabilityId,
            $startDatetime,
            $excludeId,
            $bufferMinutes
        );

        if ($scheduleError !== null) {
            return $scheduleError;
        }

        return $this->validatePreviousImpediment(
            $availabilityId,
            $startDatetime,
            $excludeId,
            $bufferMinutes
        );
    }

    /**
     * Extracts buffer data from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @param  ValidationContext  $context  The validation context
     * @return array{int, DateTimeZuluVO, DateTimeZuluVO, int|null}|null
     */
    private function extractBufferData(mixed $record, ValidationContext $context): ?array
    {
        $availabilityId = null;
        $startDatetime = null;
        $endDatetime = null;
        $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;

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

        return [$availabilityId, $startDatetime, $endDatetime, $excludeId];
    }

    /**
     * Validates buffer with previous schedule.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  int|null  $excludeId  The ID to exclude
     * @param  int  $bufferMinutes  The required buffer in minutes
     * @return ValidationErrorRecord|null Error if validation fails
     */
    private function validatePreviousSchedule(
        int $availabilityId,
        DateTimeZuluVO $startDatetime,
        ?int $excludeId,
        int $bufferMinutes
    ): ?ValidationErrorRecord {
        $previous = Schedule::where('availability_id', $availabilityId)
            ->where('end_datetime', '<=', $startDatetime->toDateTimeString())
            ->where('id', '!=', $excludeId ?? 0)
            ->orderBy('end_datetime', 'desc')
            ->first();

        if ($previous === null) {
            return null;
        }

        $prevEnd = DateTimeZuluVO::fromCarbon($previous->end_datetime);
        $actualBuffer = $startDatetime->diffInMinutes($prevEnd);

        if ($actualBuffer < $bufferMinutes) {
            return $this->createBufferError(
                'schedule',
                $previous->id,
                $prevEnd,
                $bufferMinutes,
                $actualBuffer
            );
        }

        return null;
    }

    /**
     * Validates buffer with previous impediment.
     *
     * @param  int  $availabilityId  The availability ID
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  int|null  $excludeId  The ID to exclude
     * @param  int  $bufferMinutes  The required buffer in minutes
     * @return ValidationErrorRecord|null Error if validation fails
     */
    private function validatePreviousImpediment(
        int $availabilityId,
        DateTimeZuluVO $startDatetime,
        ?int $excludeId,
        int $bufferMinutes
    ): ?ValidationErrorRecord {
        $previous = Impediment::where('availability_id', $availabilityId)
            ->where('end_datetime', '<=', $startDatetime->toDateTimeString())
            ->where('id', '!=', $excludeId ?? 0)
            ->orderBy('end_datetime', 'desc')
            ->first();

        if ($previous === null) {
            return null;
        }

        $prevEnd = DateTimeZuluVO::fromCarbon($previous->end_datetime);
        $actualBuffer = $startDatetime->diffInMinutes($prevEnd);

        if ($actualBuffer < $bufferMinutes) {
            return $this->createBufferError(
                'impediment',
                $previous->id,
                $prevEnd,
                $bufferMinutes,
                $actualBuffer
            );
        }

        return null;
    }

    /**
     * Creates an error for buffer violation.
     *
     * @param  string  $type  The event type ('schedule' or 'impediment')
     * @param  int  $previousId  The previous event ID
     * @param  DateTimeZuluVO  $prevEnd  The previous end time
     * @param  int  $bufferMinutes  The required buffer
     * @param  float  $actualBuffer  The actual buffer
     * @return ValidationErrorRecord The error record
     */
    private function createBufferError(
        string $type,
        int $previousId,
        DateTimeZuluVO $prevEnd,
        int $bufferMinutes,
        float $actualBuffer
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Buffer time of %d minutes not respected between previous %s #%d (ending at %s) and the new slot.',
                $bufferMinutes,
                $type,
                $previousId,
                $prevEnd->toDateTimeString()
            ),
            context: Associative::from([
                'previous_event_type' => $type,
                'previous_event_id' => $previousId,
                'previous_end' => $prevEnd->toDateTimeString(),
                'buffer_required' => $bufferMinutes,
                'buffer_actual' => $actualBuffer,
            ])
        );
    }
}
