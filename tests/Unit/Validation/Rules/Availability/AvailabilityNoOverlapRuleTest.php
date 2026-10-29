<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityNoOverlapRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\PhpVo\ValueObjects\DateTimeZuluVO;
use AndyDefer\PhpVo\ValueObjects\TimeZuluVO;

final class AvailabilityNoOverlapRuleTest extends IntegrationTestCase
{
    private AvailabilityNoOverlapRule $rule;

    private ValidationHelperService $helper;

    private AvailabilityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new ValidationHelperService;
        $this->rule = new AvailabilityNoOverlapRule($this->helper);
        $this->repository = $this->app->make(AvailabilityRepository::class);
    }

    public function test_supports_availability_create_and_update_operations(): void
    {
        $createContext = new ValidationContext(
            AvailabilityRecord::from([]),
            OperationType::CREATE
        );

        $updateContext = new ValidationContext(
            AvailabilityRecord::from([]),
            OperationType::UPDATE
        );

        $deleteContext = new ValidationContext(
            AvailabilityRecord::from([]),
            OperationType::DELETE
        );

        $this->assertTrue($this->rule->supports($createContext));
        $this->assertTrue($this->rule->supports($updateContext));
        $this->assertFalse($this->rule->supports($deleteContext));
    }

    public function test_returns_null_when_required_data_is_missing(): void
    {
        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
        ]);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_null_when_schedulable_type_and_id_are_missing(): void
    {
        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('09:00:00'),
            'daily_end' => TimeZuluVO::from('17:00:00'),
        ]);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_detects_overlap_with_existing_availability(): void
    {
        $existing = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('10:00:00'),
            'daily_end' => TimeZuluVO::from('12:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => '1',
        ]);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(AvailabilityNoOverlapRule::class, $result->rule);
        $this->assertStringContainsString('Availability overlaps with existing availability', $result->message);
        $this->assertStringContainsString((string) $existing->id, $result->message);
    }

    public function test_does_not_detect_overlap_for_different_schedulable_type(): void
    {
        ChronosMutationContext::withAllowed(function () {
            Availability::create([
                'name' => 'Existing',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('10:00:00'),
            'daily_end' => TimeZuluVO::from('12:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => '2',
        ]);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_does_not_detect_overlap_for_different_days(): void
    {
        ChronosMutationContext::withAllowed(function () {
            Availability::create([
                'name' => 'Existing',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['tuesday']),
            'daily_start' => TimeZuluVO::from('10:00:00'),
            'daily_end' => TimeZuluVO::from('12:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => '1',
        ]);
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_does_not_detect_overlap_with_itself_on_update(): void
    {
        $existing = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('10:00:00'),
            'daily_end' => TimeZuluVO::from('12:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => '1',
        ]);
        $context = new ValidationContext(
            $record,
            OperationType::UPDATE,
            $existing
        );

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_detects_overlap_for_update_with_different_entity(): void
    {
        $existing1 = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing 1',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '09:00:00',
                'daily_end' => '17:00:00',
                'validity_start' => '2024-01-01 00:00:00',
                'validity_end' => '2024-12-31 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $existing2 = ChronosMutationContext::withAllowed(function () {
            return Availability::create([
                'name' => 'Existing 2',
                'type' => 'test',
                'days' => ['monday'],
                'daily_start' => '10:00:00',
                'daily_end' => '12:00:00',
                'validity_start' => '2024-06-01 00:00:00',
                'validity_end' => '2024-06-30 23:59:59',
                'schedulable_type' => TestCar::class,
                'schedulable_id' => '1',
            ]);
        });

        $record = AvailabilityRecord::from([
            'days' => WeekDayCollection::fromStrings(['monday']),
            'daily_start' => TimeZuluVO::from('10:00:00'),
            'daily_end' => TimeZuluVO::from('12:00:00'),
            'validity_start' => DateTimeZuluVO::from('2024-06-01T00:00:00Z'),
            'validity_end' => DateTimeZuluVO::from('2024-06-30T23:59:59Z'),
            'schedulable_type' => TestCar::class,
            'schedulable_id' => '1',
        ]);
        $context = new ValidationContext(
            $record,
            OperationType::UPDATE,
            $existing1
        );

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability overlaps with existing availability', $result->message);
        $this->assertStringContainsString((string) $existing2->id, $result->message);
    }
}
