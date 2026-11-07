<?php

namespace App\Services;

use App\Exceptions\ShiftAlreadyOpenException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * Open a new shift. Only one shift can be open at a time.
     *
     * @throws ShiftAlreadyOpenException
     */
    public function start(): Shift
    {
        return DB::transaction(function () {
            if (Shift::query()->open()->lockForUpdate()->exists()) {
                throw new ShiftAlreadyOpenException;
            }

            return Shift::create(['started_at' => now()]);
        });
    }

    /**
     * Close a shift. Ending an already ended shift changes nothing.
     */
    public function end(Shift $shift): Shift
    {
        return DB::transaction(function () use ($shift) {
            $fresh = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($fresh->isOpen()) {
                $fresh->update(['ended_at' => now()]);
            }

            $shift->setRawAttributes($fresh->getAttributes(), true);

            return $shift;
        });
    }

    /**
     * The shift's receipt, recomputed from its orders every time, so a later cancellation shows up.
     * Product lines and the total count valid orders only; cancelled orders are reported apart.
     */
    public function summary(Shift $shift): ShiftSummary
    {
        $lines = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shift_id', $shift->id)
            ->whereNull('orders.cancelled_at')
            ->groupBy('order_items.product_name')
            ->orderBy('order_items.product_name')
            ->selectRaw('order_items.product_name as name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as amount')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'amount' => round((float) $row->amount, 3),
            ]);

        $valid = Order::query()->where('shift_id', $shift->id)->notCancelled();
        $cancelled = Order::query()->where('shift_id', $shift->id)->whereNotNull('cancelled_at');

        return new ShiftSummary(
            shift: $shift,
            startedAt: $shift->started_at,
            endedAt: $shift->ended_at,
            lines: $lines,
            ordersCount: (clone $valid)->count(),
            total: round((float) (clone $valid)->sum('total'), 3),
            cancelledCount: (clone $cancelled)->count(),
            cancelledTotal: round((float) (clone $cancelled)->sum('total'), 3),
        );
    }
}
