<?php

// src/Rules/DateTimeZuluRule.php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Rules;

use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Laravel validation rule for Zulu datetime format.
 *
 * Validates that a string is a valid ISO 8601 datetime in Zulu format (UTC).
 *
 * @example
 * use AndyDefer\LaravelChronos\Rules\DateTimeZuluRule;
 *
 * $request->validate([
 *     'start_datetime' => ['required', new DateTimeZuluRule()],
 *     'end_datetime' => ['required', new DateTimeZuluRule()],
 * ]);
 *
 * // With custom message
 * $request->validate([
 *     'start_datetime' => ['required', new DateTimeZuluRule('Invalid date format. Expected: 2024-01-15T10:00:00Z')],
 * ]);
 *
 * // Using the helper function
 * $request->validate([
 *     'start_datetime' => ['required', 'zulu_datetime'],
 * ]);
 */
final class DateTimeZuluRule implements ValidationRule
{
    /**
     * @param  string|null  $message  Custom error message
     */
    public function __construct(
        private readonly ?string $message = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The attribute being validated
     * @param  mixed  $value  The value being validated
     * @param  Closure(string): PotentiallyTranslatedString  $fail  The failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail($this->message ?? 'The :attribute must be a valid Zulu datetime (e.g., 2024-01-15T10:00:00Z).');

            return;
        }

        try {
            new DateTimeZuluVO($value);
        } catch (\InvalidArgumentException $e) {
            $fail($this->message ?? 'The :attribute must be a valid Zulu datetime (e.g., 2024-01-15T10:00:00Z).');
        }
    }

    /**
     * Get the validation message.
     *
     * @return string The validation message
     */
    public function message(): string
    {
        return $this->message ?? 'The :attribute must be a valid Zulu datetime (e.g., 2024-01-15T10:00:00Z).';
    }
}
