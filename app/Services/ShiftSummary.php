<?php

namespace App\Services;

use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A shift's receipt, computed live from its orders (see ShiftService::summary()).
 */
final readonly class ShiftSummary
{
    /**
     * @param  Collection<int, array{name: string, quantity: int, amount: float}>  $lines  valid orders only, by product name
     */
    public function __construct(
        public Shift $shift,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $endedAt,
        public Collection $lines,
        public int $ordersCount,
        public float $total,
        public int $cancelledCount,
        public float $cancelledTotal,
    ) {}
}
