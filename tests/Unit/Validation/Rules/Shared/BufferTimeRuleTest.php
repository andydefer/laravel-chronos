<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Validation\Rules\Shared;

use AndyDefer\LaravelChronos\Configs\ChronosConfig;
use AndyDefer\LaravelChronos\Contracts\Configs\ChronosConfigInterface;
use AndyDefer\LaravelChronos\Enums\OperationType;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Support\ChronosMutationContext;
use AndyDefer\LaravelChronos\Tests\Fixtures\Models\TestCar;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;
use AndyDefer\LaravelChronos\Validation\Context\ValidationContext;
use AndyDefer\LaravelChronos\Validation\Result\ValidationErrorRecord;
use AndyDefer\LaravelChronos\Validation\Rules\Shared\BufferTimeRule;
use AndyDefer\LaravelChronos\Validation\Services\ValidationHelperService;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class BufferTimeRuleTest extends IntegrationTestCase
{
    private BufferTimeRule $rule;

    private ChronosConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Set configuration via Laravel config repository
        config()->set('chronos.min_duration', 15);
        config()->set('chronos.max_duration', 240);
        config()->set('chronos.buffer_time', 30);

        $helper = new ValidationHelperService;
        $this->config = new ChronosConfig(app(ConfigRepository::class));
        $this->rule = new BufferTimeRule($helper, $this->config);
    }

    public function test_supports_schedule_and_impediment_operations(): void
    {
        $createContext = new ValidationContext(
            new ScheduleRecord,
            OperationType::CREATE
        );

        $updateContext = new ValidationContext(
            new ScheduleRecord,
            OperationType::UPDATE
        );

        $this->assertTrue($this->rule->supports($createContext));
        $this->assertTrue($this->rule->supports($updateContext));
    }

    public function test_returns_null_when_availability_id_is_missing(): void
    {
        $record = new ScheduleRecord;
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_returns_null_when_buffer_time_is_zero(): void
    {
        // Set buffer_time to 0
        config()->set('chronos.buffer_time', 0);

        $helper = new ValidationHelperService;
        $config = new ChronosConfig(app(ConfigRepository::class));
        $rule = new BufferTimeRule($helper, $config);

        // Créer availability avec contexte ouvert
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
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $rule->validate($context);

        $this->assertNull($result);

        // Restore buffer_time for other tests
        config()->set('chronos.buffer_time', 30);
    }

    public function test_returns_error_when_buffer_with_previous_schedule_is_violated(): void
    {
        // Créer availability avec contexte ouvert
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

        // Create previous schedule ending at 10:00 avec contexte ouvert
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Previous Schedule',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        // New schedule starting at 10:15 (only 15 min buffer, but 30 min required)
        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:15:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:15:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertInstanceOf(ValidationErrorRecord::class, $result);
        $this->assertEquals(BufferTimeRule::class, $result->rule);
        $this->assertStringContainsString('Buffer time of 30 minutes not respected', $result->message);
    }

    public function test_passes_when_buffer_with_previous_schedule_is_respected(): void
    {
        // Créer availability avec contexte ouvert
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

        // Create previous schedule ending at 10:00 avec contexte ouvert
        ChronosMutationContext::withAllowed(function () use ($availability) {
            Schedule::create([
                'availability_id' => $availability->id,
                'schedulable_type' => TestCar::class,
                'schedulable_id' => 1,
                'title' => 'Previous Schedule',
                'start_datetime' => '2024-01-15 09:00:00',
                'end_datetime' => '2024-01-15 10:00:00',
            ]);
        });

        // New schedule starting at 10:30 (30 min buffer, exactly the requirement)
        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:30:00Z'),
        );
        $context = new ValidationContext($record, OperationType::CREATE);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }

    public function test_excludes_current_entity_on_update(): void
    {
        // Créer availability avec contexte ouvert
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

        // Create existing schedule avec contexte ouvert
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

        // Update with same schedule (should not check buffer against itself)
        $record = new ScheduleRecord(
            availability_id: $availability->id,
            schedulable_type: TestCar::class,
            schedulable_id: 1,
            start_datetime: DateTimeZuluVO::from('2024-01-15T10:15:00Z'),
            end_datetime: DateTimeZuluVO::from('2024-01-15T11:15:00Z'),
        );
        $context = new ValidationContext($record, OperationType::UPDATE, $existing);

        $result = $this->rule->validate($context);

        $this->assertNull($result);
    }
}
