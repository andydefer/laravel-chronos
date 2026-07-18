<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Unit\ValueObjects;

use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimeZuluVOTest extends TestCase
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

    public function test_it_parses_time_string_h_i_s(): void
    {
        // Arrange
        $input = '14:30:00';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_parses_time_string_h_i(): void
    {
        // Arrange
        $input = '14:30';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_parses_zulu_time_format(): void
    {
        // Arrange
        $input = '14:30:00Z';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_parses_seconds_since_midnight(): void
    {
        // Arrange
        $input = '52200';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_parses_array_format(): void
    {
        // Arrange
        $input = '[14, 30, 0]';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_parses_array_format_without_seconds(): void
    {
        // Arrange
        $input = '[14, 30]';

        // Act
        $time = TimeZuluVO::from($input);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_returns_current_utc_time_when_null_provided(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));

        // Act
        $time = TimeZuluVO::from(null);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_it_throws_exception_for_invalid_time_string(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid time value: invalid-time');

        // Act
        TimeZuluVO::from('invalid-time');
    }

    public function test_it_throws_exception_for_invalid_seconds(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid time value: 90000');

        // Act
        TimeZuluVO::from('90000');
    }

    public function test_it_throws_exception_for_invalid_array(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid time value: [25, 0, 0]');

        // Act
        TimeZuluVO::from('[25, 0, 0]');
    }

    public function test_it_returns_same_instance_when_created_from_existing(): void
    {
        // Arrange
        $original = TimeZuluVO::from('14:30:00');

        // Act
        $duplicate = TimeZuluVO::from($original);

        // Assert
        $this->assertSame($original, $duplicate);
    }

    // ==================== FACTORY METHODS TESTS ====================

    public function test_now_returns_current_utc_time(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 30, 0, 'UTC'));

        // Act
        $now = TimeZuluVO::now();

        // Assert
        $this->assertSame('14:30:00', $now->getValue());
    }

    public function test_create_builds_time_from_parts(): void
    {
        // Arrange
        $hours = 14;
        $minutes = 30;
        $seconds = 0;

        // Act
        $time = TimeZuluVO::create($hours, $minutes, $seconds);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_create_handles_default_minutes_and_seconds(): void
    {
        // Act
        $time = TimeZuluVO::create(14);

        // Assert
        $this->assertSame('14:00:00', $time->getValue());
    }

    public function test_create_throws_exception_for_invalid_hours(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hour: 25. Must be between 0 and 23.');

        // Act
        TimeZuluVO::create(25, 0, 0);
    }

    public function test_create_throws_exception_for_invalid_minutes(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid minute: 60. Must be between 0 and 59.');

        // Act
        TimeZuluVO::create(14, 60, 0);
    }

    public function test_create_throws_exception_for_invalid_seconds(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid second: 60. Must be between 0 and 59.');

        // Act
        TimeZuluVO::create(14, 30, 60);
    }

    public function test_from_seconds_creates_correct_time(): void
    {
        // Arrange
        $seconds = 52200;

        // Act
        $time = TimeZuluVO::fromSeconds($seconds);

        // Assert
        $this->assertSame('14:30:00', $time->getValue());
    }

    public function test_from_seconds_throws_exception_for_negative_seconds(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid seconds: -1. Must be between 0 and 86399.');

        // Act
        TimeZuluVO::fromSeconds(-1);
    }

    public function test_from_seconds_throws_exception_for_exceeding_seconds(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid seconds: 86400. Must be between 0 and 86399.');

        // Act
        TimeZuluVO::fromSeconds(86400);
    }

    // ==================== VALUE RETRIEVAL TESTS ====================

    public function test_get_value_returns_time_string(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $value = $time->getValue();

        // Assert
        $this->assertSame('14:30:00', $value);
        $this->assertIsString($value);
    }

    // ==================== CONVERSION TESTS ====================

    public function test_it_converts_to_time_string(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $result = $time->toTimeString();

        // Assert
        $this->assertSame('14:30:00', $result);
    }

    public function test_it_converts_to_native_datetime_instance(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $dateTime = $time->toDateTime();

        // Assert
        $this->assertInstanceOf(DateTime::class, $dateTime);
        $this->assertSame('14:30:00', $dateTime->format('H:i:s'));
        $this->assertSame('UTC', $dateTime->getTimezone()->getName());
    }

    // ==================== FORMATTING TESTS ====================

    public function test_it_formats_with_custom_format(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertSame('14:30', $time->format('H:i'));
        $this->assertSame('02:30 PM', $time->format('h:i A'));
        $this->assertSame('14', $time->format('H'));
        $this->assertSame('30', $time->format('i'));
        $this->assertSame('00', $time->format('s'));
    }

    // ==================== GETTER TESTS ====================

    public function test_it_returns_hours_component(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertSame(14, $time->getHours());
        $this->assertIsInt($time->getHours());
    }

    public function test_it_returns_minutes_component(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertSame(30, $time->getMinutes());
        $this->assertIsInt($time->getMinutes());
    }

    public function test_it_returns_seconds_component(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:45');

        // Act & Assert
        $this->assertSame(45, $time->getSeconds());
        $this->assertIsInt($time->getSeconds());
    }

    public function test_it_returns_total_seconds_since_midnight(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:45');

        // Act
        $seconds = $time->getTotalSeconds();

        // Assert
        $this->assertSame(52245.0, $seconds);
        $this->assertIsFloat($seconds);
    }

    // ==================== COMPARISON TESTS ====================

    public function test_is_after_returns_true_when_time_is_later(): void
    {
        // Arrange
        $later = TimeZuluVO::from('14:30:00');
        $earlier = TimeZuluVO::from('13:30:00');

        // Act & Assert
        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
    }

    public function test_is_before_returns_true_when_time_is_earlier(): void
    {
        // Arrange
        $earlier = TimeZuluVO::from('13:30:00');
        $later = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
    }

    public function test_is_equal_returns_true_for_identical_times(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertTrue($time1->isEqual($time2));
    }

    public function test_is_equal_returns_false_for_different_times(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('14:31:00');

        // Act & Assert
        $this->assertFalse($time1->isEqual($time2));
    }

    public function test_compare_returns_negative_when_before(): void
    {
        // Arrange
        $earlier = TimeZuluVO::from('13:30:00');
        $later = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertLessThan(0, $earlier->compareTo($later));
        $this->assertGreaterThan(0, $later->compareTo($earlier));
        $this->assertSame(0, $earlier->compareTo($earlier));
    }

    // ==================== BETWEEN TESTS ====================

    public function test_is_between_returns_true_when_between_start_and_end(): void
    {
        // Arrange
        $start = TimeZuluVO::from('10:00:00');
        $end = TimeZuluVO::from('18:00:00');
        $middle = TimeZuluVO::from('14:00:00');

        // Act & Assert
        $this->assertTrue($middle->isBetween($start, $end));
    }

    public function test_is_between_returns_false_when_outside_range(): void
    {
        // Arrange
        $start = TimeZuluVO::from('10:00:00');
        $end = TimeZuluVO::from('18:00:00');
        $outside = TimeZuluVO::from('20:00:00');

        // Act & Assert
        $this->assertFalse($outside->isBetween($start, $end));
    }

    public function test_is_between_handles_overnight_range(): void
    {
        // Arrange
        $start = TimeZuluVO::from('22:00:00');
        $end = TimeZuluVO::from('06:00:00');
        $night = TimeZuluVO::from('02:00:00');
        $day = TimeZuluVO::from('12:00:00');

        // Act & Assert
        $this->assertTrue($night->isBetween($start, $end));
        $this->assertFalse($day->isBetween($start, $end));
    }

    public function test_is_between_respects_include_start(): void
    {
        // Arrange
        $start = TimeZuluVO::from('10:00:00');
        $end = TimeZuluVO::from('18:00:00');

        // Act & Assert
        $this->assertTrue($start->isBetween($start, $end, true));
        $this->assertFalse($start->isBetween($start, $end, false));
    }

    public function test_is_between_respects_include_end(): void
    {
        // Arrange
        $start = TimeZuluVO::from('10:00:00');
        $end = TimeZuluVO::from('18:00:00');

        // Act & Assert
        $this->assertTrue($end->isBetween($start, $end, true, true));
        $this->assertFalse($end->isBetween($start, $end, true, false));
    }

    // ==================== STATE CHECKS TESTS ====================

    public function test_is_midnight_returns_true_for_00_00_00(): void
    {
        // Arrange
        $midnight = TimeZuluVO::from('00:00:00');

        // Act & Assert
        $this->assertTrue($midnight->isMidnight());
    }

    public function test_is_midnight_returns_false_for_non_midnight(): void
    {
        // Arrange
        $time = TimeZuluVO::from('00:00:01');

        // Act & Assert
        $this->assertFalse($time->isMidnight());
    }

    public function test_is_noon_returns_true_for_12_00_00(): void
    {
        // Arrange
        $noon = TimeZuluVO::from('12:00:00');

        // Act & Assert
        $this->assertTrue($noon->isNoon());
    }

    public function test_is_noon_returns_false_for_non_noon(): void
    {
        // Arrange
        $time = TimeZuluVO::from('12:00:01');

        // Act & Assert
        $this->assertFalse($time->isNoon());
    }

    public function test_is_morning_returns_true_before_12(): void
    {
        // Arrange
        $morning = TimeZuluVO::from('10:00:00');

        // Act & Assert
        $this->assertTrue($morning->isMorning());
    }

    public function test_is_morning_returns_false_at_12(): void
    {
        // Arrange
        $noon = TimeZuluVO::from('12:00:00');

        // Act & Assert
        $this->assertFalse($noon->isMorning());
    }

    public function test_is_afternoon_returns_true_between_12_and_18(): void
    {
        // Arrange
        $afternoon = TimeZuluVO::from('14:00:00');

        // Act & Assert
        $this->assertTrue($afternoon->isAfternoon());
    }

    public function test_is_afternoon_returns_false_before_12(): void
    {
        // Arrange
        $morning = TimeZuluVO::from('10:00:00');

        // Act & Assert
        $this->assertFalse($morning->isAfternoon());
    }

    public function test_is_afternoon_returns_false_after_18(): void
    {
        // Arrange
        $evening = TimeZuluVO::from('19:00:00');

        // Act & Assert
        $this->assertFalse($evening->isAfternoon());
    }

    public function test_is_evening_returns_true_after_18(): void
    {
        // Arrange
        $evening = TimeZuluVO::from('19:00:00');

        // Act & Assert
        $this->assertTrue($evening->isEvening());
    }

    public function test_is_evening_returns_false_before_18(): void
    {
        // Arrange
        $afternoon = TimeZuluVO::from('17:00:00');

        // Act & Assert
        $this->assertFalse($afternoon->isEvening());
    }

    // ==================== ARITHMETIC TESTS ====================

    public function test_it_adds_hours_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:00');

        // Act
        $result = $time->addHours(2);

        // Assert
        $this->assertSame('16:00:00', $result->getValue());
    }

    public function test_it_adds_hours_wrapping_around_midnight(): void
    {
        // Arrange
        $time = TimeZuluVO::from('23:00:00');

        // Act
        $result = $time->addHours(2);

        // Assert
        $this->assertSame('01:00:00', $result->getValue());
    }

    public function test_it_subtracts_hours_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:00');

        // Act
        $result = $time->subHours(2);

        // Assert
        $this->assertSame('12:00:00', $result->getValue());
    }

    public function test_it_subtracts_hours_wrapping_around_midnight(): void
    {
        // Arrange
        $time = TimeZuluVO::from('01:00:00');

        // Act
        $result = $time->subHours(2);

        // Assert
        $this->assertSame('23:00:00', $result->getValue());
    }

    public function test_it_adds_minutes_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:00');

        // Act
        $result = $time->addMinutes(30);

        // Assert
        $this->assertSame('14:30:00', $result->getValue());
    }

    public function test_it_adds_minutes_wrapping_around_hour(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:45:00');

        // Act
        $result = $time->addMinutes(30);

        // Assert
        $this->assertSame('15:15:00', $result->getValue());
    }

    public function test_it_subtracts_minutes_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $result = $time->subMinutes(15);

        // Assert
        $this->assertSame('14:15:00', $result->getValue());
    }

    public function test_it_subtracts_minutes_wrapping_around_hour(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:15:00');

        // Act
        $result = $time->subMinutes(30);

        // Assert
        $this->assertSame('13:45:00', $result->getValue());
    }

    public function test_it_adds_seconds_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:00');

        // Act
        $result = $time->addSeconds(45);

        // Assert
        $this->assertSame('14:00:45', $result->getValue());
    }

    public function test_it_adds_seconds_wrapping_around_minute(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:30');

        // Act
        $result = $time->addSeconds(45);

        // Assert
        $this->assertSame('14:01:15', $result->getValue());
    }

    public function test_it_subtracts_seconds_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:45');

        // Act
        $result = $time->subSeconds(30);

        // Assert
        $this->assertSame('14:00:15', $result->getValue());
    }

    public function test_it_subtracts_seconds_wrapping_around_minute(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:00:15');

        // Act
        $result = $time->subSeconds(30);

        // Assert
        $this->assertSame('13:59:45', $result->getValue());
    }

    // ==================== DIFFERENCE TESTS ====================

    public function test_it_calculates_absolute_difference_in_seconds(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:00:00');
        $time2 = TimeZuluVO::from('14:01:30');

        // Act & Assert
        $this->assertSame(90.0, $time1->diffInSeconds($time2));
        $this->assertIsFloat($time1->diffInSeconds($time2));
    }

    public function test_it_calculates_absolute_difference_in_seconds_overnight(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('23:00:00');
        $time2 = TimeZuluVO::from('01:00:00');

        // Act & Assert
        $this->assertSame(79200.0, $time1->diffInSeconds($time2));
        $this->assertIsFloat($time1->diffInSeconds($time2));
    }

    public function test_it_calculates_absolute_difference_in_minutes(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:00:00');
        $time2 = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertSame(30, $time1->diffInMinutes($time2));
        $this->assertIsInt($time1->diffInMinutes($time2));
    }

    public function test_it_calculates_absolute_difference_in_hours(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:00:00');
        $time2 = TimeZuluVO::from('17:30:00');

        // Act & Assert
        $this->assertSame(3, $time1->diffInHours($time2));
        $this->assertIsInt($time1->diffInHours($time2));
    }

    // ==================== CARBON ACCESS TESTS ====================

    public function test_it_returns_underlying_carbon_instance(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $carbon = $time->getCarbon();

        // Assert
        $this->assertInstanceOf(CarbonInterface::class, $carbon);
        $this->assertSame('14:30:00', $carbon->format('H:i:s'));
        $this->assertSame('UTC', $carbon->getTimezone()->getName());
    }

    // ==================== IMMUTABILITY TESTS ====================

    public function test_it_creates_new_instance_when_adding_hours(): void
    {
        // Arrange
        $original = TimeZuluVO::from('14:00:00');

        // Act
        $new = $original->addHours(1);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('14:00:00', $original->getValue());
        $this->assertSame('15:00:00', $new->getValue());
    }

    public function test_it_creates_new_instance_when_subtracting_hours(): void
    {
        // Arrange
        $original = TimeZuluVO::from('14:00:00');

        // Act
        $new = $original->subHours(1);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('14:00:00', $original->getValue());
        $this->assertSame('13:00:00', $new->getValue());
    }

    public function test_it_creates_new_instance_when_adding_minutes(): void
    {
        // Arrange
        $original = TimeZuluVO::from('14:00:00');

        // Act
        $new = $original->addMinutes(15);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('14:00:00', $original->getValue());
        $this->assertSame('14:15:00', $new->getValue());
    }

    public function test_it_creates_new_instance_when_subtracting_minutes(): void
    {
        // Arrange
        $original = TimeZuluVO::from('14:30:00');

        // Act
        $new = $original->subMinutes(15);

        // Assert
        $this->assertNotSame($original, $new);
        $this->assertSame('14:30:00', $original->getValue());
        $this->assertSame('14:15:00', $new->getValue());
    }

    // ==================== CHAINING TESTS ====================

    public function test_it_chains_multiple_operations(): void
    {
        // Act
        $result = TimeZuluVO::from('10:00:00')
            ->addHours(3)
            ->addMinutes(30)
            ->subHours(1);

        // Assert
        $this->assertSame('12:30:00', $result->getValue());
    }

    public function test_it_handles_complex_chaining_with_wraparound(): void
    {
        // Act
        $result = TimeZuluVO::from('23:00:00')
            ->addHours(2)
            ->addMinutes(45)
            ->subHours(1);

        // Assert
        $this->assertSame('00:45:00', $result->getValue());
    }

    // ==================== STRING REPRESENTATION TESTS ====================

    public function test_to_string_magic_method_returns_time_string(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $string = (string) $time;

        // Assert
        $this->assertSame('14:30:00', $string);
    }

    // ==================== EDGE CASE TESTS ====================

    public function test_it_handles_midnight_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('00:00:00');

        // Act & Assert
        $this->assertSame('00:00:00', $time->getValue());
        $this->assertSame(0.0, $time->getTotalSeconds());
        $this->assertTrue($time->isMidnight());
        $this->assertFalse($time->isNoon());
    }

    public function test_it_handles_end_of_day_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('23:59:59');

        // Act & Assert
        $this->assertSame('23:59:59', $time->getValue());
        $this->assertSame(86399.0, $time->getTotalSeconds());
    }

    public function test_it_handles_noon_correctly(): void
    {
        // Arrange
        $time = TimeZuluVO::from('12:00:00');

        // Act & Assert
        $this->assertSame('12:00:00', $time->getValue());
        $this->assertTrue($time->isNoon());
        $this->assertFalse($time->isMidnight());
        $this->assertFalse($time->isMorning());
        $this->assertTrue($time->isAfternoon());
    }

    public function test_it_preserves_utc_timezone(): void
    {
        // Act
        $time = TimeZuluVO::from('14:30:00');

        // Assert
        $this->assertSame('UTC', $time->getCarbon()->getTimezone()->getName());
    }

    public function test_it_preserves_time_normalization(): void
    {
        // Arrange
        $time = TimeZuluVO::from('14:30:00');

        // Act
        $add24Hours = $time->addHours(24);

        // Assert
        $this->assertSame('14:30:00', $add24Hours->getValue());
    }

    public function test_it_handles_negative_time_operations(): void
    {
        // Arrange
        $time = TimeZuluVO::from('00:00:00');

        // Act
        $result = $time->subMinutes(1);

        // Assert
        $this->assertSame('23:59:00', $result->getValue());
    }

    public function test_it_handles_large_time_operations(): void
    {
        // Arrange
        $time = TimeZuluVO::from('12:00:00');

        // Act
        $result = $time->addHours(25);

        // Assert
        $this->assertSame('13:00:00', $result->getValue());
    }

    // ==================== NEW COMPARISON METHODS TESTS ====================

    public function test_is_after_or_equal_returns_true_when_after(): void
    {
        // Arrange
        $later = TimeZuluVO::from('14:30:00');
        $earlier = TimeZuluVO::from('13:30:00');

        // Act & Assert
        $this->assertTrue($later->isAfterOrEqual($earlier));
    }

    public function test_is_after_or_equal_returns_true_when_equal(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertTrue($time1->isAfterOrEqual($time2));
    }

    public function test_is_after_or_equal_returns_false_when_before(): void
    {
        // Arrange
        $earlier = TimeZuluVO::from('13:30:00');
        $later = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertFalse($earlier->isAfterOrEqual($later));
    }

    public function test_is_before_or_equal_returns_true_when_before(): void
    {
        // Arrange
        $earlier = TimeZuluVO::from('13:30:00');
        $later = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertTrue($earlier->isBeforeOrEqual($later));
    }

    public function test_is_before_or_equal_returns_true_when_equal(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('14:30:00');

        // Act & Assert
        $this->assertTrue($time1->isBeforeOrEqual($time2));
    }

    public function test_is_before_or_equal_returns_false_when_after(): void
    {
        // Arrange
        $later = TimeZuluVO::from('14:30:00');
        $earlier = TimeZuluVO::from('13:30:00');

        // Act & Assert
        $this->assertFalse($later->isBeforeOrEqual($earlier));
    }

    public function test_is_same_hour_returns_true_when_same_hour(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('14:45:00');

        // Act & Assert
        $this->assertTrue($time1->isSameHour($time2));
    }

    public function test_is_same_hour_returns_false_when_different_hour(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('14:30:00');
        $time2 = TimeZuluVO::from('15:30:00');

        // Act & Assert
        $this->assertFalse($time1->isSameHour($time2));
    }

    public function test_is_same_hour_returns_true_for_midnight_and_end_of_day(): void
    {
        // Arrange
        $time1 = TimeZuluVO::from('00:30:00');
        $time2 = TimeZuluVO::from('00:45:00');

        // Act & Assert
        $this->assertTrue($time1->isSameHour($time2));
    }
}
