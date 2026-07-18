<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Validation\Rules\Availability;

use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidationRule;
use AndyDefer\LaravelChronos\Enums\EntityType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;

/**
 * Validates that the availability duration meets the minimum required duration.
 *
 * Ensures that the time between daily_start and daily_end is at least
 * the configured minimum duration.
 */
final class AvailabilityMinimumDurationRule implements ValidationRule
{
    /**
     * Constructor with dependency injection.
     *
     * @param  ValidationHelperService  $helper  Helper service for validation utilities
     * @param  ChronosConfigInterface  $config  Configuration containing min duration
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
        return 'Ensures availability duration meets the minimum required duration configured in the system.';
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
     * Validate the availability duration meets minimum requirements.
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

        $dailyStart = $record->daily_start;
        $dailyEnd = $record->daily_end;

        // Skip validation if times are not set (handled by other rules)
        if ($this->areTimesMissing($dailyStart, $dailyEnd)) {
            return null;
        }

        $minDuration = $this->config->getMinDuration();
        $actualDuration = $this->helper->getTimeDurationInMinutes($dailyStart, $dailyEnd);

        if ($this->isDurationTooShort($actualDuration, $minDuration)) {
            return $this->createDurationTooShortError($minDuration, $actualDuration, $dailyStart, $dailyEnd);
        }

        return null;
    }

    /**
     * Check if daily start or end times are missing.
     *
     * @param  mixed  $dailyStart  The daily start time
     * @param  mixed  $dailyEnd  The daily end time
     * @return bool True if either time is missing
     */
    private function areTimesMissing(mixed $dailyStart, mixed $dailyEnd): bool
    {
        return $dailyStart === null || $dailyEnd === null;
    }

    /**
     * Check if the duration is too short.
     *
     * @param  int  $actualDuration  The actual duration in minutes
     * @param  int  $minDuration  The minimum allowed duration in minutes
     * @return bool True if duration is below minimum
     */
    private function isDurationTooShort(int $actualDuration, int $minDuration): bool
    {
        return $actualDuration < $minDuration;
    }

    /**
     * Create an error for insufficient duration.
     *
     * @param  int  $minDuration  The minimum required duration
     * @param  int  $actualDuration  The actual duration
     * @param  mixed  $dailyStart  The daily start time
     * @param  mixed  $dailyEnd  The daily end time
     * @return ValidationErrorRecord The error record
     */
    private function createDurationTooShortError(
        int $minDuration,
        int $actualDuration,
        mixed $dailyStart,
        mixed $dailyEnd
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability duration must be at least %d minutes. Current duration: %d minutes.',
                $minDuration,
                $actualDuration
            ),
            context: Associative::from([
                'min_duration' => $minDuration,
                'actual_duration' => $actualDuration,
                'daily_start' => $dailyStart->toTimeString(),
                'daily_end' => $dailyEnd->toTimeString(),
            ])
        );
    }
}
