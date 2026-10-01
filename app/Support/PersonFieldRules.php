<?php

namespace App\Support;

/**
 * Canonical character and format rules for person-owned records (members and
 * officers, which share the same tables).
 *
 * The member/officer creation modals cannot be the only line of defence, so
 * every rule here is mirrored in resources/react/lib/validation.ts. Keep the
 * two in sync.
 *
 * Regex patterns are delimited with `~` and the `u` modifier because they
 * contain `/` and `#`. Do not switch the `regex:` prefix form to the `!`
 * variant -- Laravel does not strip `!` from `regex` rules (only from
 * `not_regex`), so `regex:!~...~` throws "No ending delimiter '!' found".
 */
final class PersonFieldRules
{
    public const NAME_MAX = 100;

    public const SUFFIX_MAX = 50;

    public const TITLE_MAX = 50;

    public const EMAIL_MAX = 191;

    public const PHONE_MAX = 11;

    public const PHONE_MIN = 10;

    public const POSTAL_MAX = 4;

    public const GOV_ID_MAX = 64;

    public const SHORT_TEXT_MAX = 150;

    public const TEXT_MAX = 255;

    public const INCOME_MAX = 9999999999;

    /** Mirrors personal_data.title. */
    public const TITLES = [
        'Chairman',
        'Vice Chairman',
        'Secretary',
        'Treasurer',
        'Loan Officer',
        'Accountant',
        'Member',
    ];

    /** Mirrors employments.employment_type. */
    public const EMPLOYMENT_TYPES = [
        'full-time',
        'part-time',
        'self-employed',
        'freelancer',
        'government',
        'private',
        'ofw',
        'retired',
        'unemployed',
        'student',
        'homemaker',
    ];

    /** Letters (any script), spaces, apostrophes, hyphens and trailing periods. */
    public const NAME_PATTERN = '~^[\p{L}\p{M}\p{Zs}][\p{L}\p{M}\p{Zs}\x27\x{2019}\x{2013}.-]*$~u';

    /** Free text: adds digits and the punctuation that addresses and job titles legitimately use. */
    public const TEXT_PATTERN = '~^[\p{L}\p{N}\p{M}\p{Zs}.,\x27&()\/+#\x{2013}-]+$~u';

    /** Philippine mobile or landline with area code, digits only -- no +, spaces or dashes. */
    public const PHONE_PATTERN = '~^[0-9]{10,11}$~';

    public const POSTAL_CODE_PATTERN = '~^[0-9]{4}$~';

    /** Dashes are optional so values already stored undashed still validate. */
    public const NATIONAL_ID_PATTERN = '~^[0-9]{4}-?[0-9]{4}-?[0-9]{4}-?[0-9]{4}$~';

    public const TIN_PATTERN = '~^[0-9]{3}-?[0-9]{3}-?[0-9]{3}-?[0-9]{3}$~';

    public const SSS_PATTERN = '~^[0-9]{2}-?[0-9]{7}-?[0-9]$~';

    public const PHILHEALTH_PATTERN = '~^[0-9]{2}-?[0-9]{9}-?[0-9]$~';

    /** PSGC-style codes; kept alphanumeric because the format is not fixed in the UI. */
    public const LOCAL_ID_PATTERN = '~^[A-Za-z0-9-]+$~';

    public const URL_PATTERN = '~^https?://[^\s/$.?#].[^\s]*$~i';

    public static function name(bool $required, int $max = self::NAME_MAX): array
    {
        return self::stringRule($required, $max, self::NAME_PATTERN);
    }

    public static function text(bool $required, int $max = self::TEXT_MAX): array
    {
        return self::stringRule($required, $max, self::TEXT_PATTERN);
    }

    public static function email(bool $required, array $extra = []): array
    {
        return array_values(array_merge(
            [$required ? 'required' : 'nullable', 'email', 'max:'.self::EMAIL_MAX],
            $extra
        ));
    }

    public static function phone(bool $required): array
    {
        $rules = [$required ? 'required' : 'nullable', 'string', 'regex:'.self::PHONE_PATTERN];

        if ($required) {
            $rules[] = 'min:'.self::PHONE_MIN;
        }

        $rules[] = 'max:'.self::PHONE_MAX;

        return $rules;
    }

    public static function postalCode(): array
    {
        return ['nullable', 'string', 'regex:'.self::POSTAL_CODE_PATTERN, 'max:'.self::POSTAL_MAX];
    }

    public static function nationalId(): array
    {
        return self::govId(self::NATIONAL_ID_PATTERN);
    }

    public static function tin(): array
    {
        return self::govId(self::TIN_PATTERN);
    }

    public static function sss(): array
    {
        return self::govId(self::SSS_PATTERN);
    }

    public static function philhealth(): array
    {
        return self::govId(self::PHILHEALTH_PATTERN);
    }

    public static function localId(): array
    {
        return self::stringRule(false, self::GOV_ID_MAX, self::LOCAL_ID_PATTERN);
    }

    public static function facebookLink(): array
    {
        return self::stringRule(false, self::TEXT_MAX, self::URL_PATTERN);
    }

    public static function title(): array
    {
        return ['nullable', 'string', 'max:'.self::TITLE_MAX, 'in:'.implode(',', self::TITLES)];
    }

    public static function employmentType(): array
    {
        return ['nullable', 'string', 'max:50', 'in:'.implode(',', self::EMPLOYMENT_TYPES)];
    }

    public static function income(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:'.self::INCOME_MAX];
    }

    public static function birthDate(bool $required, int $minimumAge): array
    {
        return [
            $required ? 'required' : 'nullable',
            'date',
            'before:today',
            'before_or_equal:'.now()->subYears($minimumAge)->toDateString(),
        ];
    }

    /**
     * Custom messages keyed by `field.rule`. Merge into the controller's
     * second `validate()` argument so operators see actionable text instead of
     * "The first name format is invalid."
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $nameMessage = 'may only contain letters, spaces, apostrophes and hyphens.';
        $textMessage = 'may only contain letters, numbers and basic punctuation.';

        return [
            'first_name.regex' => 'First name '.$nameMessage,
            'mid_name.regex' => 'Middle name '.$nameMessage,
            'last_name.regex' => 'Last name '.$nameMessage,
            'suffix.regex' => 'Suffix '.$nameMessage,
            'religion.regex' => 'Religion '.$nameMessage,
            'beneficiaries.*.first_name.regex' => 'First name '.$nameMessage,
            'beneficiaries.*.last_name.regex' => 'Last name '.$nameMessage,
            'beneficiaries.*.relationship.regex' => 'Relationship '.$nameMessage,

            'birthplace.regex' => 'Place of birth '.$textMessage,
            'education.regex' => 'Education '.$textMessage,
            'country.regex' => 'Country '.$textMessage,
            'region.regex' => 'Region '.$textMessage,
            'province.regex' => 'Province '.$textMessage,
            'city.regex' => 'City/Municipality '.$textMessage,
            'city_municipality.regex' => 'City/Municipality '.$textMessage,
            'barangay.regex' => 'Barangay '.$textMessage,
            'street.regex' => 'Street address '.$textMessage,
            'employer_name.regex' => 'Employer name '.$textMessage,
            'position.regex' => 'Position '.$textMessage,

            'phone_number.regex' => 'Phone number must be '.self::PHONE_MIN.'-'.self::PHONE_MAX.' digits using numbers only.',
            'phone_no.regex' => 'Phone number must be '.self::PHONE_MIN.'-'.self::PHONE_MAX.' digits using numbers only.',
            'postal_code.regex' => 'Postal code must be '.self::POSTAL_MAX.' digits.',
            'zip_code.regex' => 'ZIP code must be '.self::POSTAL_MAX.' digits.',

            'national_id.regex' => 'National ID must be 16 digits, optionally grouped as XXXX-XXXX-XXXX-XXXX.',
            'tin.regex' => 'TIN must be 12 digits, optionally grouped as XXX-XXX-XXX-XXX.',
            'sss.regex' => 'SSS must be 10 digits, optionally grouped as XX-XXXXXXX-X.',
            'philhealth.regex' => 'PhilHealth number must be 12 digits, optionally grouped as XX-XXXXXXXXX-X.',
            'barangay_id.regex' => 'Barangay ID may only contain letters, numbers and hyphens.',

            'facebook_link.regex' => 'Enter a valid link starting with http:// or https://.',
            'facebook.regex' => 'Enter a valid link starting with http:// or https://.',

            'title.in' => 'Select a valid title.',
            'employment_type.in' => 'Select a valid employment type.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function stringRule(bool $required, int $max, ?string $pattern, ?array $leading = null): array
    {
        $rules = $leading ?? [$required ? 'required' : 'nullable', 'string'];

        if ($pattern !== null) {
            $rules[] = 'regex:'.$pattern;
        }

        $rules[] = 'max:'.$max;

        return array_values($rules);
    }

    /**
     * @return array<int, string>
     */
    private static function govId(string $pattern): array
    {
        return ['nullable', 'string', 'regex:'.$pattern, 'max:'.self::GOV_ID_MAX];
    }
}
