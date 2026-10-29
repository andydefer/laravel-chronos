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

final class AvailabilityOwnershipValidationRule implements ValidationRule
{
    public function getDescription(): string
    {
        return 'Validates that the referenced availability exists and belongs to the correct schedulable entity.';
    }

    public function supports(ValidationContext $context): bool
    {
        return ($context->getEntityType() === EntityType::SCHEDULE
            || $context->getEntityType() === EntityType::IMPEDIMENT)
            && $context->isCreate();
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
            return $this->createAvailabilityNotFoundError($availabilityId);
        }

        if ($record instanceof ImpedimentRecord) {
            return null;
        }

        if (! $record instanceof ScheduleRecord) {
            return null;
        }

        $schedulableType = $record->schedulable_type;
        $schedulableId = $record->schedulable_id;

        if ($schedulableType === null || $schedulableId === null) {
            return null;
        }

        if ($this->isOwnershipMismatch($availability, $schedulableType, $schedulableId)) {
            return $this->createOwnershipMismatchError(
                $availabilityId,
                $schedulableType,
                $schedulableId,
                $availability
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
        string $schedulableType,
        int|string $schedulableId
    ): bool {
        return $availability->schedulable_type !== $schedulableType
            || (string) $availability->schedulable_id !== (string) $schedulableId;
    }

    private function createAvailabilityNotFoundError(int|string $availabilityId): ValidationErrorRecord
    {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf('Availability #%s does not exist.', $availabilityId),
            context: Associative::from([
                'availability_id' => $availabilityId,
            ])
        );
    }

    private function createOwnershipMismatchError(
        int|string $availabilityId,
        string $schedulableType,
        int|string $schedulableId,
        Availability $availability
    ): ValidationErrorRecord {
        return new ValidationErrorRecord(
            rule: self::class,
            message: sprintf(
                'Availability #%s does not belong to this schedulable entity (%s#%s).',
                $availabilityId,
                $schedulableType,
                $schedulableId
            ),
            context: Associative::from([
                'availability_id' => $availabilityId,
                'schedulable_type' => $schedulableType,
                'schedulable_id' => $schedulableId,
                'availability_schedulable_type' => $availability->schedulable_type,
                'availability_schedulable_id' => $availability->schedulable_id,
            ])
        );
    }
}
