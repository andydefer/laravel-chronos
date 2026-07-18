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
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

/**
 * Enforces maximum duration limits on schedules and impediments.
 *
 * Prevents events from exceeding the configured maximum duration,
 * ensuring that no single event blocks the schedule for too long.
 */
final class MaxDurationRule implements ValidationRule
{
    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Ensures that event duration does not exceed the configured maximum duration.';
    }

    /**
     * Constructor with dependency injection.
     *
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     * @param  ChronosConfigInterface  $config  Configuration containing max duration
     */
    public function __construct(
        private readonly ValidationHelperService $helper,
        private readonly ChronosConfigInterface $config
    ) {}

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
     * Validate that the duration does not exceed the maximum allowed.
     *
     * @param  ValidationContext  $context  The validation context containing the record
     * @return ValidationErrorRecord|null An error record if validation fails, null otherwise
     */
    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        // Extract time data from the record
        $timeData = $this->extractTimeData($record);

        if ($timeData === null) {
            return null;
        }

        [$startDatetime, $endDatetime] = $timeData;

        $maxDuration = $this->config->getMaxDuration();
        $actualDuration = $this->helper->getDurationInMinutes($startDatetime, $endDatetime);

        if ($actualDuration > $maxDuration) {
            return $this->createDurationExceededError(
                $startDatetime,
                $endDatetime,
                $actualDuration,
                $maxDuration
            );
        }

        return null;
    }

    /**
     * Extract time data from the record.
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
     * Format duration in minutes to human-readable string.
     *
     * @param  int  $minutes  The duration in minutes
     * @return string Human-readable duration
     */
    private function formatDuration(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return sprintf('%d hours %d minutes', $hours, $remainingMinutes);
        }

        if ($hours > 0) {
            return sprintf('%d hours', $hours);
        }

        return sprintf('%d minutes', $remainingMinutes);
    }

    /**
     * Create an error for exceeded duration.
     *
     * @param  DateTimeZuluVO  $startDatetime  The start datetime
     * @param  DateTimeZuluVO  $endDatetime  The end datetime
     * @param  int  $actualDuration  The actual duration in minutes
     * @param  int  $maxDuration  The maximum allowed duration in minutes
     * @return ValidationErrorRecord The error record
     */
    private function createDurationExceededError(
        DateTimeZuluVO $startDatetime,
        DateTimeZuluVO $endDatetime,
        int $actualDuration,
        int $maxDuration
    ): ValidationErrorRecord {
        $actualFormatted = $this->formatDuration($actualDuration);
        $maxFormatted = $this->formatDuration($maxDuration);

        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Duration (%s) exceeds maximum allowed duration (%s).',
                $actualFormatted,
                $maxFormatted
            ),
            context: Associative::from([
                'duration' => $actualDuration,
                'max_duration' => $maxDuration,
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
            ])
        );
    }
}
