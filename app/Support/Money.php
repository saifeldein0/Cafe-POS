<?php

namespace App\Support;

/**
 * Tunisian dinar formatting (3 decimals), without depending on ext-intl.
 */
class Money
{
    public const SUFFIX = ' DT';

    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 3, '.', ' ').self::SUFFIX;
    }
}
