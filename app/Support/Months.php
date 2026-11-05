<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Months
{
    /**
     * Recent months as ['2026-11' => 'Novembre 2026', ...], newest first.
     *
     * @return array<string, string>
     */
    public static function options(int $count = 24): array
    {
        $months = [];
        $month = CarbonImmutable::now()->startOfMonth();

        for ($i = 0; $i < $count; $i++) {
            $months[$month->format('Y-m')] = self::label($month);
            $month = $month->subMonth();
        }

        return $months;
    }

    public static function label(CarbonImmutable $month): string
    {
        return ucfirst($month->locale('fr')->translatedFormat('F Y'));
    }

    public static function parse(?string $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return CarbonImmutable::createFromFormat('!Y-m', $value);
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
