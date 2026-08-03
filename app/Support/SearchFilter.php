<?php

namespace App\Support;

class SearchFilter
{
    /**
     * Escape LIKE wildcard characters so user input cannot match more rows
     * than intended (e.g. a bare "%" matching every record).
     */
    public static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
