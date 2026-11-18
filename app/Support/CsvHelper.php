<?php

namespace App\Support;

/**
 * Spreadsheet-safe CSV cell sanitization and UTF-8 BOM output helpers.
 */
class CsvHelper
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    public static function safe(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $str = (string) $value;
        if (is_numeric($str)) {
            return $str;
        }

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $str) === 1 ? "'{$str}" : $str;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function safeRow(array $row): array
    {
        return array_map(static fn (mixed $cell): string => self::safe($cell), $row);
    }
}
