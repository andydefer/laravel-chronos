<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Availability;

use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Availability\AvailabilityMinimumDurationRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class AvailabilityMinimumDurationRuleTest extends IntegrationTestCase
{
    private AvailabilityMinimumDurationRule $rule;

    private ValidationHelperService $helper;

    private ChronosConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Set configuration with entity-specific durations
        config()->set('chronos.min_durations', [
            'availability' => 15,
            'schedule' => 15,
            'impediment' => 15,
            'slot_search' => 5,
        ]);
        config()->set('chronos.max_duration', 240);
        config()->set('chronos.buffer_time', 0);

        $this->helper = new ValidationHelperService;
        $this->config = new ChronosConfig(app(ConfigRepository::class));
        $this->rule = new AvailabilityMinimumDurationRule($this->helper, $this->config);
    }

    public function test_supports_availability_create_and_update_operations(): void
    {
        $createContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::CREATE
        );

        $updateContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::UPDATE
        );

        $deleteContext = new ValidationContext(
            new AvailabilityRecord,
            OperationType::DELETE
        );

        $this->assertTrue($this->rule->supports($createContext));
        $this->assertTrue($this->rule->supports($updateContext));
        $this->assertFalse($this->rule->supports($deleteContext));
    }

    public function test_returns_null_when_daily_times_are_missing(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_error_when_duration_is_below_minimum(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:05:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(AvailabilityMinimumDurationRule::class, $result->rule);
        $this->assertStringContainsString('Availability duration must be at least 15 minutes', $result->message);
        $this->assertStringContainsString('Current duration: 5 minutes', $result->message);
    }

    public function test_returns_error_when_duration_is_zero(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability duration must be at least 15 minutes', $result->message);
        $this->assertStringContainsString('Current duration: 0 minutes', $result->message);
    }

    public function test_passes_validation_when_duration_meets_minimum(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:30:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_passes_validation_when_duration_exceeds_minimum(): void
    {
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('17:00:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::UPDATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_respects_configuration_changes(): void
    {
        // Change min duration for availability to 60 minutes
        config()->set('chronos.min_durations.availability', 60);

        $helper = new ValidationHelperService;
        $config = new ChronosConfig(app(ConfigRepository::class));
        $rule = new AvailabilityMinimumDurationRule($helper, $config);

        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:30:00'), // 30 minutes < 60 min
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability duration must be at least 60 minutes', $result->message);

        // Restore configuration for other tests
        config()->set('chronos.min_durations.availability', 15);
    }

    public function test_uses_availability_specific_duration(): void
    {
        // Set different durations for different entities
        config()->set('chronos.min_durations', [
            'availability' => 30,
            'schedule' => 15,
            'impediment' => 10,
            'slot_search' => 5,
        ]);

        $helper = new ValidationHelperService;
        $config = new ChronosConfig(app(ConfigRepository::class));
        $rule = new AvailabilityMinimumDurationRule($helper, $config);

        // Availability duration = 20 minutes should fail (min 30 for availability)
        $record = new AvailabilityRecord(
            days: WeekDayCollection::fromStrings(['monday']),
            daily_start: TimeZuluVO::from('09:00:00'),
            daily_end: TimeZuluVO::from('09:20:00'),
            schedulable_type: TestCar::class,
            schedulable_id: 1,
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertStringContainsString('Availability duration must be at least 30 minutes', $result->message);

        // Restore configuration
        config()->set('chronos.min_durations', [
            'availability' => 15,
            'schedule' => 15,
            'impediment' => 15,
            'slot_search' => 5,
        ]);
    }
}
