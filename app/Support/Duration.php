<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Short shift durations: "7 h 05", "45 min".
 */
class Duration
{
    public static function between(CarbonInterface $start, ?CarbonInterface $end = null): string
    {
        $minutes = max(0, (int) floor($start->diffInMinutes($end ?? now())));
        $hours = intdiv($minutes, 60);

        return $hours === 0
            ? $minutes.' min'
            : sprintf('%d h %02d', $hours, $minutes % 60);
    }
}
