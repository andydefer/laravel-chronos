<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Validation;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

final class ValidatorTest extends IntegrationTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        ChronosMutationContext::withAllowed(function () {
            TestCar::create([
                'model' => 'Test Model',
                'license_plate' => 'TEST123',
                'type' => 'sedan',
                'capacity' => 5,
            ]);
        });

        $this->validator = $this->app->make(ValidatorInterface::class);
    }

    private function createFullAvailabilityRecord(): AvailabilityRecord
    {
        return new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
    }

    // ============================================================
    // 1. AvailabilityRequiredFieldsRule
    // ============================================================

    public function test_availability_required_fields_validation_passes_with_all_fields(): void
    {
        $record = $this->createFullAvailabilityRecord();

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_required_fields_validation_fails_when_name_missing(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();
        $this->assertStringContainsString('name', $messages[0]);
    }

    public function test_availability_required_fields_validation_skips_on_update(): void
    {
        $record = new AvailabilityRecord(
            name: 'Updated Name',
        );

        $result = $this->validator->validateRecord($record, OperationType::UPDATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();
        $this->assertStringContainsString('At least one day must be specified', $messages[0]);
    }

    // ============================================================
    // 2. AvailabilityDaysFormatRule
    // ============================================================

    public function test_availability_days_format_validation_passes_with_valid_days(): void
    {
        $record = $this->createFullAvailabilityRecord();

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_days_format_validation_fails_with_empty_days(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: new WeekDayCollection,
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('At least one day must be specified', $result->getMessages()[0]);
    }

    public function test_availability_days_format_validation_fails_with_duplicate_days(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday', 'tuesday', 'monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Duplicate day(s) found: monday', $result->getMessages()[0]);
    }

    // ============================================================
    // 3. DaysWithinValidityPeriodRule
    // ============================================================

    public function test_days_within_validity_period_validation_passes(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-07T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_days_within_validity_period_validation_fails(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['saturday', 'sunday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-05T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('saturday, sunday', $result->getMessages()[0]);
        $this->assertStringContainsString('not within the validity period', $result->getMessages()[0]);
    }

    public function test_days_within_validity_period_validation_skips_when_no_validity_dates(): void
    {
        // Ce test est en UPDATE, car pour CREATE les dates de validité sont obligatoires
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        // Utiliser UPDATE pour que la règle AvailabilityValidDateRangeRule n'impose pas les dates
        $result = $this->validator->validateRecord($record, OperationType::UPDATE);

        // La règle DaysWithinValidityPeriodRule s'arrête si validity_start est null
        // Donc pas d'erreur
        $this->assertFalse($result->hasErrors());
    }

    // ============================================================
    // 4. AvailabilityNoOverlapRule
    // ============================================================

    public function test_availability_no_overlap_validation_passes_with_no_conflict(): void
    {
        $record = $this->createFullAvailabilityRecord();

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_no_overlap_validation_fails_with_conflict(): void
    {
        $existing = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord(
            name: 'Overlapping Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('10:00:00'),
            daily_end: TimeZuluVO::from('12:00:00'),
            validity_start: DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Availability overlaps with existing availability', $result->getMessages()[0]);
        $this->assertStringContainsString((string) $existing->id, $result->getMessages()[0]);
    }

    public function test_availability_no_overlap_validation_excludes_self_on_update(): void
    {
        $existing = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord(
            name: 'Updated Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('10:00:00'),
            daily_end: TimeZuluVO::from('12:00:00'),
            validity_start: DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::UPDATE, $existing);

        $this->assertFalse($result->hasErrors());
    }

    // ============================================================
    // 5. AvailabilityMinimumDurationRule
    // ============================================================

    public function test_availability_minimum_duration_validation_passes(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:30:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_minimum_duration_validation_fails(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:05:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Availability duration must be at least 15 minutes', $result->getMessages()[0]);
    }

    // ============================================================
    // 6. AvailabilityValidDateRangeRule
    // ============================================================

    public function test_availability_valid_date_range_validation_passes(): void
    {
        $record = $this->createFullAvailabilityRecord();

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_valid_date_range_validation_fails_when_daily_start_after_end(): void
    {
        // Ce test n'a plus de sens car cross-day est autorisé
        // On le modifie pour tester un cas de cross-day invalide avec days = ['monday'] (un seul jour)
        // La règle CrossDayAvailabilityRule va détecter que c'est invalide
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']), // Un seul jour
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'), // Cross-day
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        // La règle CrossDayAvailabilityRule détecte que 1 seul jour n'est pas valide
        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Availability crosses midnight but days array is not consecutive')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected CrossDayAvailabilityRule error not found in: '.implode(' | ', $messages));
    }

    public function test_availability_valid_date_range_validation_fails_when_validity_start_after_end(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-08T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-01-01T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Validity start date must be before validity end date')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected error message not found in: '.implode(' | ', $messages));
    }

    // ============================================================
    // 7. NoFutureBookingsOnDeleteRule
    // ============================================================

    public function test_no_future_bookings_on_delete_passes_with_no_future_bookings(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord;
        $result = $this->validator->validateRecord($record, OperationType::DELETE, $availability);

        $this->assertFalse($result->hasErrors());
    }

    public function test_no_future_bookings_on_delete_fails_with_future_bookings(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            $avail = Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            Schedule::create([
                'availability_id' => $avail->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Future Schedule',
                'start_datetime' => now()->addDays(10),
                'end_datetime' => now()->addDays(10)->addHour(),
            ]);

            return $avail;
        });

        $record = new AvailabilityRecord;
        $result = $this->validator->validateRecord($record, OperationType::DELETE, $availability);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Cannot delete availability that has future bookings', $result->getMessages()[0]);
    }

    // ============================================================
    // 8. CrossDayAvailabilityRule
    // ============================================================

    public function test_cross_day_availability_validation_passes_with_consecutive_days(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday', 'tuesday']),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        // DEBUG: Afficher les erreurs
        if ($result->hasErrors()) {
            echo "\n=== DEBUG test_cross_day_availability_validation_passes_with_consecutive_days ===\n";
            echo "Erreurs trouvées:\n";
            foreach ($result->getMessages() as $message) {
                echo '- '.$message."\n";
            }
            echo "==========================================\n";
        }

        $this->assertFalse($result->hasErrors());
    }

    public function test_cross_day_availability_validation_fails_with_single_day(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday']),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Availability crosses midnight but days array is not consecutive')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected CrossDayAvailabilityRule error not found');
    }

    public function test_cross_day_availability_validation_fails_with_non_consecutive_days(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            daily_start: TimeZuluVO::from('22:00:00'),
            daily_end: TimeZuluVO::from('06:00:00'),
            days: WeekDayCollection::fromStrings(['monday', 'wednesday']),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Availability crosses midnight but days array is not consecutive')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected CrossDayAvailabilityRule error not found');
    }

    // ============================================================
    // 9. SchedulableExistsRule
    // ============================================================

    public function test_schedulable_exists_validation_passes_when_entity_exists(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_schedulable_exists_validation_fails_when_entity_not_exists(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 99999,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Schedulable entity #99999')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected SchedulableExistsRule error not found');
    }

    public function test_schedulable_exists_validation_fails_when_class_not_exists(): void
    {
        $record = new AvailabilityRecord(
            name: 'Test Availability',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: 'Invalid\\Class\\That\\Does\\Not\\Exist',
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Schedulable class "Invalid\\Class\\That\\Does\\Not\\Exist" does not exist')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected SchedulableExistsRule error not found');
    }

    // ============================================================
    // 10. EntityOwnershipConsistencyRule
    // ============================================================

    public function test_entity_ownership_consistency_validation_passes(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_entity_ownership_consistency_validation_fails(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: 'App\\Models\\DifferentEntity',
            schedulable_id: 2,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('does not match the parent availability entity', $result->getMessages()[0]);
    }

    // ============================================================
    // 11. AvailabilityOwnershipValidationRule
    // ============================================================

    public function test_availability_ownership_validation_passes(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_availability_ownership_validation_fails_when_availability_not_exists(): void
    {
        $record = new ScheduleRecord(
            availability_id: 99999,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Availability #99999 does not exist', $result->getMessages()[0]);
    }

    public function test_availability_ownership_validation_fails_when_availability_belongs_to_different_entity(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: 'App\\Models\\DifferentEntity',
            schedulable_id: 2,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $messages = $result->getMessages();

        $found = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'does not match the parent availability entity') ||
                str_contains($message, 'does not belong to this schedulable entity')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected ownership error not found in: '.implode(' | ', $messages));
    }

    // ============================================================
    // 12. TimeSlotWithinAvailabilityRule
    // ============================================================

    public function test_time_slot_within_availability_validation_passes(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_time_slot_within_availability_validation_fails_when_day_not_allowed(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday', 'wednesday', 'friday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-16T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-16T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Day "tuesday" is not allowed', $result->getMessages()[0]);
    }

    public function test_time_slot_within_availability_validation_fails_when_outside_validity_period(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-01-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-02-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-02-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('outside the validity period', $result->getMessages()[0]);
    }

    // ============================================================
    // 13. NoTemporalConflictRule
    // ============================================================

    public function test_no_temporal_conflict_validation_passes(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            $avail = Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            Schedule::create([
                'availability_id' => $avail->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);

            return $avail;
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T12:30:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_no_temporal_conflict_validation_fails_with_schedule_conflict(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            $avail = Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            Schedule::create([
                'availability_id' => $avail->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);

            return $avail;
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('conflicts with existing schedule', $result->getMessages()[0]);
    }

    public function test_no_temporal_conflict_validation_excludes_self_on_update(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $existing = ChronosMutationContext::withAllowed(function () use ($availability) {
            return Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Existing Schedule',
                'start_datetime' => '2024-01-15 10:00:00',
                'end_datetime' => '2024-01-15 11:00:00',
            ]);
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::UPDATE, $existing);

        $this->assertFalse($result->hasErrors());
    }

    // ============================================================
    // 14. TimeSlotChronologyRule
    // ============================================================

    public function test_time_slot_chronology_validation_passes(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_time_slot_chronology_validation_fails_when_start_after_end(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Start datetime must be before end datetime', $result->getMessages()[0]);
    }

    public function test_time_slot_chronology_validation_fails_when_start_equal_end(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Start datetime must be before end datetime', $result->getMessages()[0]);
    }

    // ============================================================
    // 15. BufferTimeRule
    // ============================================================

    public function test_buffer_time_validation_passes(): void
    {
        config()->set('chronos.buffer_time', 30);

        $availability = ChronosMutationContext::withAllowed(function () {
            $avail = Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            Schedule::create([
                'availability_id' => $avail->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Previous Schedule',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            return $avail;
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_buffer_time_validation_fails(): void
    {
        config()->set('chronos.buffer_time', 30);

        $availability = ChronosMutationContext::withAllowed(function () {
            $avail = Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);

            Schedule::create([
                'availability_id' => $avail->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Previous Schedule',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);

            return $avail;
        });

        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:15:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:15:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Buffer time of 30 minutes not respected', $result->getMessages()[0]);
    }

    // ============================================================
    // 16. MaxDurationRule
    // ============================================================

    public function test_max_duration_validation_passes(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T12:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_max_duration_validation_fails(): void
    {
        $record = new ScheduleRecord(
            start_datetime: DateTimeZuluVO::from('2024-01-15T09:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T15:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('exceeds maximum allowed duration', $result->getMessages()[0]);
    }

    // ============================================================
    // 17. Combined validation with multiple errors
    // ============================================================

    public function test_combined_validation_returns_multiple_errors(): void
    {
        $record = new AvailabilityRecord(
            daily_start: TimeZuluVO::from('17:00:00'),
            daily_end: TimeZuluVO::from('09:00:00'),
            days: WeekDayCollection::fromStrings(['invalid_day']),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $errors = $result->getMessages();

        $this->assertGreaterThanOrEqual(2, count($errors));
    }

    // ============================================================
    // 18. Validation with existing entity (UPDATE scenario)
    // ============================================================

    public function test_update_validation_with_existing_entity_passes(): void
    {
        $existing = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord(
            name: 'Updated Name',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::UPDATE, $existing);

        $this->assertFalse($result->hasErrors());
    }

    public function test_update_validation_with_existing_entity_fails_on_overlap_with_other(): void
    {
        $availability1 = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Availability 1',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '12:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $availability2 = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Availability 2',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '13:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new AvailabilityRecord(
            name: 'Updated Availability 1',
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('10:00:00'),
            daily_end: TimeZuluVO::from('14:00:00'),
            validity_start: DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
            validity_end: DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );

        $result = $this->validator->validateRecord($record, OperationType::UPDATE, $availability1);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Availability overlaps with existing availability', $result->getMessages()[0]);
    }

    // ============================================================
    // 19. Impediment validation tests
    // ============================================================

    public function test_impediment_validation_within_availability_passes(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ImpedimentRecord(
            availability_id: $availability->id,
            reason: 'Test Impediment',
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T12:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertFalse($result->hasErrors());
    }

    public function test_impediment_validation_fails_when_day_not_allowed(): void
    {
        $availability = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Test Availability',
                'type' => 'test',
                'days' => ['monday', 'wednesday', 'friday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
            ]);
        });

        $record = new ImpedimentRecord(
            availability_id: $availability->id,
            reason: 'Test Impediment',
            start_datetime: DateTimeZuluVO::from('2024-01-16T10:00:00Z'), // Tuesday
            end_datetime: DateTimeZuluVO::from('2024-01-16T12:00:00Z'),
        );

        $result = $this->validator->validateRecord($record, OperationType::CREATE);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Day "tuesday" is not allowed', $result->getMessages()[0]);
    }
}
