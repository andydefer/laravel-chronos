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

final class EntityOwnershipConsistencyRule implements ValidationRule
{
    public function getDescription(): string
    {
        return 'Ensures that schedules and impediments belong to the same entity as their parent availability.';
    }

    public function supports(ValidationContext $context): bool
    {
        return $context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT;
    }

    public function validate(ValidationContext $context): ?ValidationErrorRecord
    {
        $record = $context->getRecord();

        $availabilityId = $this->extractAvailabilityId($record);

        if ($availabilityId === null) {
            return null;
        }

        $availability = Availability::find($availabilityId);

        if ($availability === null) {
            return null;
        }

        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        if (! $record instanceof ScheduleRecord) {
            return null;
        }

        $recordType = $record->schedulable_type;
        $recordId = $record->schedulable_id;

        if ($recordType === null || $recordId === null) {
            return null;
        }

        if ($this->isOwnershipMismatch($availability, $recordType, $recordId)) {
            return $this->createOwnershipMismatchError(
                $recordType,
                $recordId,
                $availability,
                $availabilityId
            );
        }

        return null;
    }

    private function extractAvailabilityId(mixed $record): int|string|null
    {
        return match (true) {
            $record instanceof ScheduleRecord => $record->availability_id,
            $record instanceof ImpedimentRecord => $record->availability_id,
            default => null,
        };
    }

    private function isOwnershipMismatch(
        Availability $availability,
        string $recordType,
        int|string $recordId
    ): bool {
        return $recordType !== $availability->schedulable_type
            || $recordId !== $availability->schedulable_id;
    }

    private function createOwnershipMismatchError(
        string $recordType,
        int|string $recordId,
        Availability $availability,
        int|string $availabilityId
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'The schedule entity (%s#%s) does not match the parent availability entity (%s#%s).',
                $recordType,
                $recordId,
                $availability->schedulable_type,
                $availability->schedulable_id
            ),
            context: Associative::from([
                'schedule_schedulable_type' => $recordType,
                'schedule_schedulable_id' => $recordId,
                'availability_schedulable_type' => $availability->schedulable_type,
                'availability_schedulable_id' => $availability->schedulable_id,
                'availability_id' => $availabilityId,
            ])
        );
    }
}
