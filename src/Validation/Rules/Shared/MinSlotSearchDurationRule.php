<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Shared;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Validates that a slot search duration meets the minimum required duration.
 *
 * This rule prevents users from searching for slots that are too short,
 * which could generate excessive results and cause performance issues.
 * It applies to both schedule and impediment searches.
 *
 * @example
 * $rule = new MinSlotSearchDurationRule($config);
 * $context = new ValidationContext($record, OperationType::CREATE);
 * $error = $rule->validate($context);
 *
 * if ($error !== null) {
 *     // Handle duration too short error
 * }
 */
final class MinSlotSearchDurationRule implements ValidationRule
{
    /**
     * @param  ChronosConfigInterface  $config  Configuration containing minimum slot search duration
     */
    public function __construct(
        private readonly ChronosConfigInterface $config
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Ensures that slot search duration meets the minimum required duration to prevent performance issues.';
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
     * Validates that the slot duration meets the minimum required duration.
     *
     * @throws \RuntimeException If the record is not a ScheduleRecord or ImpedimentRecord
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $timeData = $this->extractTimeData($record);

        if ($timeData === null) {
            return null;
        }

        [$startDatetime, $endDatetime] = $timeData;

        $minDuration = $this->config->getMinSlotSearchDuration();
        $actualDuration = $this->calculateDurationInMinutes($startDatetime, $endDatetime);

        if ($actualDuration < $minDuration) {
            return $this->createDurationTooShortError(
                $startDatetime,
                $endDatetime,
                $actualDuration,
                $minDuration
            );
        }

        return null;
    }

    /**
     * Extracts time data from the record.
     *
     * @param  mixed  $record  The record to extract from
     * @return array{DateTimeZuluVO, DateTimeZuluVO}|null Array of [start, end] or null
     */
    private function extractTimeData(mixed $record): ?array
    {
        $startDatetime = null;
        $endDatetime = null;

        if ($record instanceof ScheduleRecord) {
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        } elseif ($record instanceof ImpedimentRecord) {
            $startDatetime = $record->start_datetime;
            $endDatetime = $record->end_datetime;
        }

        if ($startDatetime === null || $endDatetime === null) {
            return null;
        }

        return [$startDatetime, $endDatetime];
    }

    /**
     * Calculates the duration between two datetimes in minutes.
     *
     * @param  DateTimeZuluVO  $start  The start datetime
     * @param  DateTimeZuluVO  $end  The end datetime
     * @return int The duration in minutes
     */
    private function calculateDurationInMinutes(DateTimeZuluVO $start, DateTimeZuluVO $end): int
    {
        return (int) $start->diffInMinutes($end);
    }

    /**
     * Creates an error for duration too short.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  int  $actualDuration  The actual duration in minutes
     * @param  int  $minDuration  The minimum allowed duration in minutes
     * @return ValidationErrorRecord The error record
     */
    private function createDurationTooShortError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        int $actualDuration,
        int $minDuration
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Slot duration (%d minutes) is too short. Minimum allowed duration for slot search is %d minutes.',
                $actualDuration,
                $minDuration
            ),
            context: Associative::from([
                'actual_duration' => $actualDuration,
                'min_duration' => $minDuration,
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
            ])
        );
    }
}
