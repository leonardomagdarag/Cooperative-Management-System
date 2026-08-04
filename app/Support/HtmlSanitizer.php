<?php

namespace App\Support;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'span', 'div', 'img', 'hr', 'sub', 'sup', 'small', 'code', 'pre',
    ];

    private static function removeTagWithContent(string $html): string
    {
        $pattern = '~<(script|style|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[^>]*>.*?</\1\s*>~is';
        $cleaned = preg_replace($pattern, '', $html);

        return $cleaned === null ? $html : $cleaned;
    }

    private static function stripUnclosedDangerousTags(string $html): string
    {
        $pattern = '~<(script|style|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[^>]*/?>~i';
        $cleaned = preg_replace($pattern, '', $html);

        return $cleaned === null ? $html : $cleaned;
    }

    private static function stripEventHandlers(string $html): string
    {
        $pattern = '~\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i';
        $cleaned = preg_replace($pattern, '', $html);

        return $cleaned === null ? $html : $cleaned;
    }

    private static function stripDangerousUrls(string $html): string
    {
        $cleaned = $html;

        $cleaned = preg_replace('~href\s*=\s*(?:"javascript:[^"]*"|\'javascript:[^\']*\')~i', 'href="#"', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('~src\s*=\s*(?:"(?:javascript|data):[^"]*"|\'(?:javascript|data):[^\']*\')~i', 'src=""', $cleaned) ?? $cleaned;

        return $cleaned;
    }

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = self::removeTagWithContent($html);
        $html = self::stripUnclosedDangerousTags($html);
        $html = self::stripEventHandlers($html);
        $html = self::stripDangerousUrls($html);

        return trim(strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>'));
    }
}
