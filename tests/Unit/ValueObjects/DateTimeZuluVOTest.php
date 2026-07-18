<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\ValueObjects;

use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateTimeZuluVOTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ==================== CREATION TESTS ====================

    public function test_it_parses_zulu_format(): void
    {
        // Arrange
        $input = '2024-01-15T14:30:00Z';

        // Act
        $date = DateTimeZuluVO::from($input);

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $date->getValue());
    }

    public function test_it_parses_iso8601_with_timezone_offset(): void
    {
        // Arrange
        $input = '2024-01-15T15:30:00+01:00';

        // Act
        $date = DateTimeZuluVO::from($input);

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $date->getValue());
    }

    public function test_it_parses_database_datetime_string(): void
    {
        // Arrange
        $input = '2024-01-15 14:30:00';

        // Act
        $date = DateTimeZuluVO::from($input);

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $date->getValue());
    }

    public function test_it_parses_date_only_string(): void
    {
        // Arrange
        $input = '2024-01-15';

        // Act
        $date = DateTimeZuluVO::from($input);

        // Assert
        $this->assertSame('2024-01-15T00:00:00Z', $date->getValue());
    }

    public function test_it_returns_current_utc_datetime_when_null_provided(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));

        // Act
        $date = DateTimeZuluVO::from(null);

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $date->getValue());
    }

    public function test_it_throws_exception_for_invalid_string(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid datetime value: invalid-date');

        // Act
        DateTimeZuluVO::from('invalid-date');
    }

    // ==================== FACTORY METHODS TESTS ====================

    public function test_now_returns_current_utc_datetime(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));

        // Act
        $now = DateTimeZuluVO::now();

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $now->getValue());
    }

    public function test_today_returns_midnight_utc_of_current_day(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));

        // Act
        $today = DateTimeZuluVO::today();

        // Assert
        $this->assertSame('2024-01-15T00:00:00Z', $today->getValue());
    }

    public function test_create_builds_datetime_in_utc(): void
    {
        // Arrange
        $year = 2024;
        $month = 1;
        $day = 15;
        $hour = 14;
        $minute = 30;
        $second = 0;

        // Act
        $date = DateTimeZuluVO::create($year, $month, $day, $hour, $minute, $second);

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $date->getValue());
    }

    public function test_create_handles_default_time_values(): void
    {
        // Act
        $date = DateTimeZuluVO::create(2024, 1, 15);

        // Assert
        $this->assertSame('2024-01-15T00:00:00Z', $date->getValue());
    }

    // ==================== VALUE RETRIEVAL TESTS ====================

    public function test_get_value_returns_zulu_string(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $value = $date->getValue();

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $value);
        $this->assertIsString($value);
    }

    // ==================== CONVERSION TESTS ====================

    public function test_it_converts_to_database_string(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->toDateTimeString();

        // Assert
        $this->assertSame('2024-01-15 14:30:00', $result);
    }

    public function test_it_converts_to_date_string(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->toDateString();

        // Assert
        $this->assertSame('2024-01-15', $result);
    }

    public function test_it_converts_to_time_string(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->toTimeString();

        // Assert
        $this->assertSame('14:30:00', $result);
    }

    public function test_it_converts_to_unix_timestamp(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->toTimestamp();

        // Assert
        $this->assertIsInt($result);
    }

    public function test_it_converts_to_native_datetime_instance(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $dateTime = $date->toDateTime();

        // Assert
        $this->assertInstanceOf(DateTime::class, $dateTime);
        $this->assertSame('2024-01-15 14:30:00', $dateTime->format('Y-m-d H:i:s'));
    }

    public function test_it_converts_to_native_datetime_immutable_instance(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $dateTime = $date->toDateTimeImmutable();

        // Assert
        $this->assertInstanceOf(DateTimeImmutable::class, $dateTime);
        $this->assertSame('2024-01-15 14:30:00', $dateTime->format('Y-m-d H:i:s'));
    }

    // ==================== FORMATTING TESTS ====================

    public function test_it_formats_with_custom_format(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame('15/01/2024', $date->format('d/m/Y'));
        $this->assertSame('14:30', $date->format('H:i'));
        $this->assertSame('January', $date->format('F'));
        $this->assertSame('2024', $date->format('Y'));
    }

    // ==================== COMPARISON TESTS ====================

    public function test_is_after_returns_true_when_date_is_later(): void
    {
        // Arrange
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');

        // Act & Assert
        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
    }

    public function test_is_before_returns_true_when_date_is_earlier(): void
    {
        // Arrange
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
    }

    public function test_is_equal_returns_true_for_identical_datetimes(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertTrue($date1->isEqual($date2));
    }

    public function test_is_equal_returns_true_for_same_moment_in_different_timezones(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T15:30:00+01:00');

        // Act & Assert
        $this->assertTrue($date1->isEqual($date2));
    }

    public function test_is_equal_returns_false_for_different_datetimes(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-16T14:30:00Z');

        // Act & Assert
        $this->assertFalse($date1->isEqual($date2));
    }

    // ==================== STATE CHECKS TESTS ====================

    public function test_is_past_returns_true_for_past_date(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-01-01T00:00:00Z');

        // Act & Assert
        $this->assertTrue($date->isPast());
    }

    public function test_is_past_returns_false_for_future_date(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-02-01T00:00:00Z');

        // Act & Assert
        $this->assertFalse($date->isPast());
    }

    public function test_is_future_returns_true_for_future_date(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-02-01T00:00:00Z');

        // Act & Assert
        $this->assertTrue($date->isFuture());
    }

    public function test_is_future_returns_false_for_past_date(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-01-01T00:00:00Z');

        // Act & Assert
        $this->assertFalse($date->isFuture());
    }

    public function test_is_today_returns_true_for_today(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act & Assert
        $this->assertTrue($date->isToday());
    }

    public function test_is_tomorrow_returns_true_for_tomorrow(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-01-16T00:00:00Z');

        // Act & Assert
        $this->assertTrue($date->isTomorrow());
    }

    public function test_is_yesterday_returns_true_for_yesterday(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));
        $date = DateTimeZuluVO::from('2024-01-14T00:00:00Z');

        // Act & Assert
        $this->assertTrue($date->isYesterday());
    }

    // ==================== ARITHMETIC TESTS ====================

    public function test_it_adds_days_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->addDays(1);

        // Assert
        $this->assertSame('2024-01-16T00:00:00Z', $result->getValue());
    }

    public function test_it_subtracts_days_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->subDays(1);

        // Assert
        $this->assertSame('2024-01-14T00:00:00Z', $result->getValue());
    }

    public function test_it_adds_hours_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->addHours(3);

        // Assert
        $this->assertSame('2024-01-15T03:00:00Z', $result->getValue());
    }

    public function test_it_subtracts_hours_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        // Act
        $result = $date->subHours(3);

        // Assert
        $this->assertSame('2024-01-15T09:00:00Z', $result->getValue());
    }

    public function test_it_adds_minutes_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->addMinutes(30);

        // Assert
        $this->assertSame('2024-01-15T00:30:00Z', $result->getValue());
    }

    public function test_it_subtracts_minutes_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:30:00Z');

        // Act
        $result = $date->subMinutes(15);

        // Assert
        $this->assertSame('2024-01-15T00:15:00Z', $result->getValue());
    }

    public function test_it_adds_months_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->addMonths(1);

        // Assert
        $this->assertSame('2024-02-15T00:00:00Z', $result->getValue());
    }

    public function test_it_subtracts_months_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-02-15T00:00:00Z');

        // Act
        $result = $date->subMonths(1);

        // Assert
        $this->assertSame('2024-01-15T00:00:00Z', $result->getValue());
    }

    public function test_it_adds_years_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->addYears(1);

        // Assert
        $this->assertSame('2025-01-15T00:00:00Z', $result->getValue());
    }

    public function test_it_subtracts_years_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $result = $date->subYears(1);

        // Assert
        $this->assertSame('2023-01-15T00:00:00Z', $result->getValue());
    }

    // ==================== DIFFERENCE TESTS ====================

    public function test_it_calculates_absolute_difference_in_seconds(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T00:01:30Z');

        // Act & Assert
        $this->assertSame(90.0, $date1->diffInSeconds($date2));
        $this->assertIsFloat($date1->diffInSeconds($date2));
    }

    public function test_it_calculates_absolute_difference_in_minutes(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T00:30:00Z');

        // Act & Assert
        $this->assertSame(30.0, $date1->diffInMinutes($date2));
        $this->assertIsFloat($date1->diffInMinutes($date2));
    }

    public function test_it_calculates_absolute_difference_in_hours(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T03:30:00Z');

        // Act & Assert
        $this->assertSame(3.5, $date1->diffInHours($date2));
        $this->assertIsFloat($date1->diffInHours($date2));
    }

    public function test_it_calculates_absolute_difference_in_days(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-18T00:00:00Z');

        // Act & Assert
        $this->assertSame(3.0, $date1->diffInDays($date2));
        $this->assertIsFloat($date1->diffInDays($date2));
    }

    public function test_it_calculates_absolute_difference_in_months(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2024-03-15T00:00:00Z');

        // Act & Assert
        $this->assertSame(2.0, $date1->diffInMonths($date2));
        $this->assertIsFloat($date1->diffInMonths($date2));
    }

    public function test_it_calculates_absolute_difference_in_years(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
        $date2 = DateTimeZuluVO::from('2026-01-15T00:00:00Z');

        // Act & Assert
        $this->assertSame(2.0, $date1->diffInYears($date2));
        $this->assertIsFloat($date1->diffInYears($date2));
    }

    // ==================== GETTER TESTS ====================

    public function test_it_returns_year_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame(2024, $date->getYear());
        $this->assertIsInt($date->getYear());
    }

    public function test_it_returns_month_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame(1, $date->getMonth());
        $this->assertIsInt($date->getMonth());
    }

    public function test_it_returns_day_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame(15, $date->getDay());
        $this->assertIsInt($date->getDay());
    }

    public function test_it_returns_hour_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame(14, $date->getHour());
        $this->assertIsInt($date->getHour());
    }

    public function test_it_returns_minute_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertSame(30, $date->getMinute());
        $this->assertIsInt($date->getMinute());
    }

    public function test_it_returns_second_component(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:45Z');

        // Act & Assert
        $this->assertSame(45, $date->getSecond());
        $this->assertIsInt($date->getSecond());
    }

    public function test_it_returns_iso_day_of_week(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act & Assert
        $this->assertSame(1, $date->getDayOfWeek());
        $this->assertIsInt($date->getDayOfWeek());
    }

    public function test_it_returns_week_of_year(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act & Assert
        $this->assertSame(3, $date->getWeekOfYear());
        $this->assertIsInt($date->getWeekOfYear());
    }

    // ==================== BOUNDARY TESTS ====================

    public function test_start_of_day_returns_midnight_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->startOfDay();

        // Assert
        $this->assertSame('2024-01-15T00:00:00Z', $result->getValue());
    }

    public function test_end_of_day_returns_23_59_59_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->endOfDay();

        // Assert
        $this->assertSame('2024-01-15T23:59:59Z', $result->getValue());
    }

    public function test_start_of_month_returns_first_day_at_midnight_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->startOfMonth();

        // Assert
        $this->assertSame('2024-01-01T00:00:00Z', $result->getValue());
    }

    public function test_end_of_month_returns_last_day_at_23_59_59_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->endOfMonth();

        // Assert
        $this->assertSame('2024-01-31T23:59:59Z', $result->getValue());
    }

    public function test_start_of_year_returns_first_day_at_midnight_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->startOfYear();

        // Assert
        $this->assertSame('2024-01-01T00:00:00Z', $result->getValue());
    }

    public function test_end_of_year_returns_last_day_at_23_59_59_utc(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $result = $date->endOfYear();

        // Assert
        $this->assertSame('2024-12-31T23:59:59Z', $result->getValue());
    }

    // ==================== CARBON ACCESS TESTS ====================

    public function test_it_returns_underlying_carbon_instance(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $carbon = $date->getCarbon();

        // Assert
        $this->assertInstanceOf(CarbonInterface::class, $carbon);
        $this->assertSame('2024-01-15 14:30:00', $carbon->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $carbon->getTimezone()->getName());
    }

    // ==================== IMMUTABILITY TESTS ====================

    public function test_it_creates_new_instance_when_adding_days(): void
    {
        // Arrange
        $original = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $new = $original->addDays(1);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('2024-01-15T00:00:00Z', $original->getValue());
        $this->assertSame('2024-01-16T00:00:00Z', $new->getValue());
    }

    public function test_it_creates_new_instance_when_subtracting_days(): void
    {
        // Arrange
        $original = DateTimeZuluVO::from('2024-01-15T00:00:00Z');

        // Act
        $new = $original->subDays(1);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('2024-01-15T00:00:00Z', $original->getValue());
        $this->assertSame('2024-01-14T00:00:00Z', $new->getValue());
    }

    // ==================== CHAINING TESTS ====================

    public function test_it_chains_multiple_operations(): void
    {
        // Act
        $result = DateTimeZuluVO::from('2024-01-15T00:00:00Z')
            ->addDays(3)
            ->addHours(5)
            ->subDays(1);

        // Assert
        $this->assertSame('2024-01-17T05:00:00Z', $result->getValue());
    }

    // ==================== STRING REPRESENTATION TESTS ====================

    public function test_to_string_magic_method_returns_zulu(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act
        $string = (string) $date;

        // Assert
        $this->assertSame('2024-01-15T14:30:00Z', $string);
    }

    // ==================== EDGE CASE TESTS ====================

    public function test_it_preserves_utc_timezone(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-15T14:30:00+01:00');

        // Act & Assert
        $this->assertSame('2024-01-15T13:30:00Z', $date->getValue());
        $this->assertSame('UTC', $date->getCarbon()->getTimezone()->getName());
    }

    public function test_it_handles_end_of_month_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-01-31T00:00:00Z');

        // Act
        $result = $date->addMonths(1);

        // Assert
        $this->assertSame('2024-03-02T00:00:00Z', $result->getValue());
    }

    public function test_it_handles_leap_year_correctly(): void
    {
        // Arrange
        $date = DateTimeZuluVO::from('2024-02-28T00:00:00Z');

        // Act
        $result = $date->addDays(1);

        // Assert
        $this->assertSame('2024-02-29T00:00:00Z', $result->getValue());
    }

    // ==================== NEW COMPARISON METHODS TESTS ====================

    public function test_is_after_or_equal_returns_true_when_after(): void
    {
        // Arrange
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');

        // Act & Assert
        $this->assertTrue($later->isAfterOrEqual($earlier));
    }

    public function test_is_after_or_equal_returns_true_when_equal(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertTrue($date1->isAfterOrEqual($date2));
    }

    public function test_is_after_or_equal_returns_false_when_before(): void
    {
        // Arrange
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertFalse($earlier->isAfterOrEqual($later));
    }

    public function test_is_before_or_equal_returns_true_when_before(): void
    {
        // Arrange
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertTrue($earlier->isBeforeOrEqual($later));
    }

    public function test_is_before_or_equal_returns_true_when_equal(): void
    {
        // Arrange
        $date1 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $date2 = DateTimeZuluVO::from('2024-01-15T14:30:00Z');

        // Act & Assert
        $this->assertTrue($date1->isBeforeOrEqual($date2));
    }

    public function test_is_before_or_equal_returns_false_when_after(): void
    {
        // Arrange
        $later = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
        $earlier = DateTimeZuluVO::from('2024-01-14T14:30:00Z');

        // Act & Assert
        $this->assertFalse($later->isBeforeOrEqual($earlier));
    }

    public function test_is_between_returns_true_when_between(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $middle = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        // Act & Assert
        $this->assertTrue($middle->isBetween($start, $end));
    }

    public function test_is_between_returns_false_when_outside(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $outside = DateTimeZuluVO::from('2024-01-15T16:00:00Z');

        // Act & Assert
        $this->assertFalse($outside->isBetween($start, $end));
    }

    public function test_is_between_respects_include_start_true(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertTrue($start->isBetween($start, $end, true, true));
    }

    public function test_is_between_respects_include_start_false(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertFalse($start->isBetween($start, $end, false, true));
    }

    public function test_is_between_respects_include_end_true(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertTrue($end->isBetween($start, $end, true, true));
    }

    public function test_is_between_respects_include_end_false(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertFalse($end->isBetween($start, $end, true, false));
    }

    public function test_is_between_with_both_excluded(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');
        $middle = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

        // Act & Assert
        $this->assertTrue($middle->isBetween($start, $end, false, false));
    }

    public function test_is_between_with_both_excluded_and_equal_to_start(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertFalse($start->isBetween($start, $end, false, false));
    }

    public function test_is_between_with_both_excluded_and_equal_to_end(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertFalse($end->isBetween($start, $end, false, false));
    }

    public function test_is_between_handles_inclusive_start_only(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertTrue($start->isBetween($start, $end, true, false));
        $this->assertFalse($end->isBetween($start, $end, true, false));
    }

    public function test_is_between_handles_inclusive_end_only(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T14:00:00Z');

        // Act & Assert
        $this->assertFalse($start->isBetween($start, $end, false, true));
        $this->assertTrue($end->isBetween($start, $end, false, true));
    }

    public function test_is_between_with_different_days(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-17T14:00:00Z');
        $middle = DateTimeZuluVO::from('2024-01-16T12:00:00Z');

        // Act & Assert
        $this->assertTrue($middle->isBetween($start, $end));
    }

    public function test_is_between_with_start_after_end_returns_false(): void
    {
        // Arrange
        $start = DateTimeZuluVO::from('2024-01-17T14:00:00Z');
        $end = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
        $middle = DateTimeZuluVO::from('2024-01-16T12:00:00Z');

        // Act & Assert
        $this->assertFalse($middle->isBetween($start, $end));
    }
}
