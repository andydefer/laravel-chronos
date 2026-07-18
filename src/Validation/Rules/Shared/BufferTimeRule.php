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
 * the start of the next event, preventing back-to-back bookings.
 */
final class BufferTimeRule implements ValidationRule
{
    /**
     * Constructor with dependency injection.
     *
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     * @param  ChronosConfigInterface  $config  Configuration containing buffer time
     */
    public function __construct(
        private readonly ValidationHelperService $helper,
        private readonly ChronosConfigInterface $config
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Enforces buffer time between consecutive events on the same availability.';
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
     * Validate that buffer time is respected.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract buffer data from the record
        $bufferData = $this->extractBufferData($record, $context);

        if ($bufferData === null) {
            return null;
        }

        [$availabilityId, $startDatetime, $endDatetime, $excludeId] = $bufferData;

        $bufferMinutes = $this->config->getBufferTime();

        // Skip validation if buffer is 0 or negative
        if ($bufferMinutes <= 0) {
            return null;
        }

        // Check buffer with previous schedule
        $previousScheduleError = $this->validatePreviousSchedule(
            $availabilityId,
            $startDatetime,
            $excludeId,
            $bufferMinutes
        );

        if ($previousScheduleError !== null) {
            return $previousScheduleError;
        }

        // Check buffer with previous impediment
        $previousImpedimentError = $this->validatePreviousImpediment(
            $availabilityId,
            $startDatetime,
            $excludeId,
            $bufferMinutes
        );

        if ($previousImpedimentError !== null) {
            return $previousImpedimentError;
        }

        return null;
    }

    /**
     * Extract buffer data from the record.
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
        $excludeId = null;

        if ($record instanceof ScheduleRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
            $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;
        } elseif ($record instanceof ImpedimentRecord) {
            $availabilityId = $record->availability_id;
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
            $excludeId = $context->hasExistingEntity() ? $context->getExistingEntity()?->id : null;
        }

        if ($availabilityId === null || $startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$availabilityId, $startDatetime, $endDatetime, $excludeId];
    }

    /**
     * Validate buffer with previous schedule.
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
            return $this->createScheduleBufferError(
                $bufferMinutes,
                $previous->id,
                $prevEnd,
                $actualBuffer
            );
        }

        return null;
    }

    /**
     * Validate buffer with previous impediment.
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
            return $this->createImpedimentBufferError(
                $bufferMinutes,
                $previous->id,
                $prevEnd,
                $actualBuffer
            );
        }

        return null;
    }

    /**
     * Create an error for schedule buffer violation.
     *
     * @param  int  $bufferMinutes  The required buffer
     * @param  int  $previousId  The previous schedule ID
     * @param  DateTimeZuluVO  $prevEnd  The previous end time
     * @param  int  $actualBuffer  The actual buffer
     * @return ValidationErrorRecord The error record
     */
    private function createScheduleBufferError(
        int $bufferMinutes,
        int $previousId,
        DateTimeZuluVO $prevEnd,
        float $actualBuffer
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Buffer time of %d minutes not respected between schedule #%d (ending at %s) and the new slot.',
                $bufferMinutes,
                $previousId,
                $prevEnd->toDateTimeString()
            ),
            context: Associative::from([
                'previous_schedule_id' => $previousId,
                'previous_end' => $prevEnd->toDateTimeString(),
                'buffer_required' => $bufferMinutes,
                'buffer_actual' => $actualBuffer,
            ])
        );
    }

    /**
     * Create an error for impediment buffer violation.
     *
     * @param  int  $bufferMinutes  The required buffer
     * @param  int  $previousId  The previous impediment ID
     * @param  DateTimeZuluVO  $prevEnd  The previous end time
     * @param  int  $actualBuffer  The actual buffer
     * @return ValidationErrorRecord The error record
     */
    private function createImpedimentBufferError(
        int $bufferMinutes,
        int $previousId,
        DateTimeZuluVO $prevEnd,
        float $actualBuffer
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Buffer time of %d minutes not respected between impediment #%d (ending at %s) and the new slot.',
                $bufferMinutes,
                $previousId,
                $prevEnd->toDateTimeString()
            ),
            context: Associative::from([
                'previous_impediment_id' => $previousId,
                'previous_end' => $prevEnd->toDateTimeString(),
                'buffer_required' => $bufferMinutes,
                'buffer_actual' => $actualBuffer,
            ])
        );
    }
}
