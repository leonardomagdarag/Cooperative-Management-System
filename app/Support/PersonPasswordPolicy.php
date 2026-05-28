<?php

namespace App\Support;

use Closure;

final class PersonPasswordPolicy
{
    public const MIN_LENGTH = 6;

    private const LETTER_NUMBER_REGEX = '/^(?=.*[A-Za-z])(?=.*\d).+$/';

    private const HTML_PATTERN = '(?=.*[A-Za-z])(?=.*\d).+';

    private const PREDICTABLE_PASSWORD_MESSAGE = 'Avoid passwords that reuse your name with simple number sequences.';

    private const REQUIREMENT_MESSAGE = 'Use at least 6 characters and combine letters with numbers.';

    private const GUIDANCE_TEXT = 'Use at least 6 characters and combine letters with numbers. Add uppercase letters and special characters to make it stronger. Avoid names, birthdays, member IDs, and simple sequences like 12345.';

    private const HELPER_TEXT = 'Use letters and numbers to meet the minimum requirement; uppercase letters and symbols make it stronger.';

    public static function passwordRules(bool $isEdit, array $nameTokens): array
    {
        return [
            $isEdit ? 'nullable' : 'required',
            'string',
            'min:' . self::MIN_LENGTH,
            'regex:' . self::LETTER_NUMBER_REGEX,
            function (string $attribute, mixed $value, Closure $fail) use ($nameTokens) {
                if (blank($value)) {
                    return;
                }

                if (self::looksPredictable((string) $value, $nameTokens)) {
                    $fail(self::predictablePasswordMessage());
                }
            },
        ];
    }

    public static function confirmationRules(bool $isEdit): string
    {
        return $isEdit
            ? 'nullable|same:form.password'
            : 'required|same:form.password';
    }

    public static function normalizedNameTokens(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => strtolower(preg_replace('/[^a-z]+/i', '', (string) $value) ?? ''))
            ->filter(fn ($value) => strlen($value) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    public static function looksPredictable(string $password, array $nameTokens): bool
    {
        $normalizedPassword = strtolower(preg_replace('/[^a-z0-9]+/i', '', $password) ?? '');

        if ($normalizedPassword === '' || $nameTokens === []) {
            return false;
        }

        foreach ($nameTokens as $token) {
            if (str_contains($normalizedPassword, $token) && preg_match('/\d{3,}/', $normalizedPassword)) {
                return true;
            }
        }

        return false;
    }

    public static function htmlPattern(): string
    {
        return self::HTML_PATTERN;
    }

    public static function guidanceText(): string
    {
        return self::GUIDANCE_TEXT;
    }

    public static function helperText(): string
    {
        return self::HELPER_TEXT;
    }

    public static function requirementMessage(): string
    {
        return self::REQUIREMENT_MESSAGE;
    }

    public static function predictablePasswordMessage(): string
    {
        return self::PREDICTABLE_PASSWORD_MESSAGE;
    }

    public static function confirmationRequiredMessage(): string
    {
        return 'Please confirm your password.';
    }

    public static function confirmationMismatchMessage(): string
    {
        return 'Password confirmation does not match.';
    }
}