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
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;

final class BufferTimeRule implements ValidationRule
{
    public function __construct(
        private readonly ValidationHelperService $helper,
        private readonly ChronosConfigInterface $config
    ) {}

    public function getDescription(): string
    {
        return 'Enforces buffer time between consecutive events on the same availability.';
    }

    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && ($context->isCreate() || $context->isUpdate());
    }

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
     * @return array{int|string, DateTimeZuluVO, DateTimeZuluVO, int|string|null}|null
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

    private function validatePreviousSchedule(
        int|string $availabilityId,
        DateTimeZuluVO $startDatetime,
        int|string|null $excludeId,
        int $bufferMinutes
    ): ?ValidationErrorRecord {
        $query = Schedule::where('availability_id', $availabilityId)
            ->where('end_datetime', '<=', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $previous = $query->orderBy('end_datetime', 'desc')->first();

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

    private function validatePreviousImpediment(
        int|string $availabilityId,
        DateTimeZuluVO $startDatetime,
        int|string|null $excludeId,
        int $bufferMinutes
    ): ?ValidationErrorRecord {
        $query = Impediment::where('availability_id', $availabilityId)
            ->where('end_datetime', '<=', $startDatetime->toDateTimeString());

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $previous = $query->orderBy('end_datetime', 'desc')->first();

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

    private function createBufferError(
        string $type,
        int|string $previousId,
        DateTimeZuluVO $prevEnd,
        int $bufferMinutes,
        float $actualBuffer
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Buffer time of %d minutes not respected between previous %s #%s (ending at %s) and the new slot.',
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
