<?php

// tests/Unit/Rules/DateTimeZuluRuleTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\Rules;

use AndyDefer\LaravelChronos\Rules\DateTimeZuluRule;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Framework\TestCase;

final class DateTimeZuluRuleTest extends TestCase
{
    private DateTimeZuluRule $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new DateTimeZuluRule;
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(ValidationRule::class, $this->rule);
    }

    public function test_valid_zulu_datetime_passes(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '2024-01-15T10:00:00Z', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_valid_zulu_datetime_with_offset_passes(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '2024-01-15T10:00:00+01:00', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_valid_database_datetime_passes(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '2024-01-15 10:00:00', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_valid_date_only_passes(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '2024-01-15', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_empty_string_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '', function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_null_value_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', null, function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_invalid_datetime_string_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', 'not a date', function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_invalid_date_format_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', '2024/01/15 25:00:00', function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_arbitrary_string_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', 'not a datetime', function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_integer_value_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', 12345, function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_boolean_value_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', true, function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_array_value_fails(): void
    {
        $failed = false;

        $this->rule->validate('start_datetime', ['2024-01-15T10:00:00Z'], function ($message) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('must be a valid Zulu datetime', (string) $message);
        });

        $this->assertTrue($failed);
    }

    public function test_custom_message_is_used(): void
    {
        $customMessage = 'Custom error message for Zulu datetime';
        $rule = new DateTimeZuluRule($customMessage);
        $failed = false;

        $rule->validate('start_datetime', 'invalid', function ($message) use (&$failed, $customMessage) {
            $failed = true;
            $this->assertEquals($customMessage, $message);
        });

        $this->assertTrue($failed);
    }

    public function test_message_method_returns_custom_message(): void
    {
        $customMessage = 'Custom validation message';
        $rule = new DateTimeZuluRule($customMessage);

        $this->assertEquals($customMessage, $rule->message());
    }

    public function test_message_method_returns_default_message(): void
    {
        $rule = new DateTimeZuluRule;

        $this->assertStringContainsString('must be a valid Zulu datetime', $rule->message());
        $this->assertStringContainsString('2024-01-15T10:00:00Z', $rule->message());
    }
}
