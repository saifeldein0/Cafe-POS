<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cash-basis monthly figures: net profit = sales − purchases − prorated fixed expenses.
 * Cancelled orders (and their stock movements) are excluded everywhere.
 */
class MonthlyReport
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(CarbonInterface $month)
    {
        $this->start = CarbonImmutable::instance($month)->startOfMonth();
        $this->end = $this->start->addMonth();
    }

    public static function for(int $year, int $month): self
    {
        return new self(CarbonImmutable::create($year, $month, 1));
    }

    public static function current(): self
    {
        return new self(CarbonImmutable::now());
    }

    /**
     * Per-product sales with an estimated cost from the current recipes.
     * `incomplete` is true when the product (or its recipe) is unknown/partial: cost is then a lower bound.
     *
     * @return Collection<int, object{product_id: ?int, product_name: string, quantity: int, revenue: float, estimated_cost: float, margin: float, incomplete: bool}>
     */
    public function productSales(): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.cancelled_at')
            ->where('orders.created_at', '>=', $this->start)
            ->where('orders.created_at', '<', $this->end)
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->orderBy('order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) AS quantity, ROUND(SUM(order_items.line_total), 3) AS revenue')
            ->get();

        $products = Product::query()
            ->whereKey($rows->pluck('product_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($products) {
            $product = $row->product_id ? $products->get($row->product_id) : null;
            $unitCost = $product?->recipeCost();
            $quantity = (int) $row->quantity;
            $revenue = (float) $row->revenue;
            $cost = round(($unitCost ?? 0) * $quantity, 3);

            return (object) [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => $quantity,
                'revenue' => $revenue,
                'estimated_cost' => $cost,
                'margin' => round($revenue - $cost, 3),
                'incomplete' => $product === null || $product->hasIncompleteRecipe(),
            ];
        });
    }

    public function totalSales(): float
    {
        return (float) DB::table('orders')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $this->start)
            ->where('created_at', '<', $this->end)
            ->selectRaw('ROUND(COALESCE(SUM(total), 0), 3) AS total')
            ->value('total');
    }

    public function purchasesTotal(): float
    {
        return (float) DB::table('purchases')
            ->where('purchased_at', '>=', $this->start->toDateString())
            ->where('purchased_at', '<', $this->end->toDateString())
            ->selectRaw('ROUND(COALESCE(SUM(total_cost), 0), 3) AS total')
            ->value('total');
    }

    /**
     * Active expenses with their monthly share (amount / period_months).
     *
     * @return Collection<int, object{name: string, amount: float, period_months: int, monthly: float}>
     */
    public function fixedExpenseLines(): Collection
    {
        return Expense::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Expense $expense) => (object) [
                'name' => $expense->name,
                'amount' => (float) $expense->amount,
                'period_months' => $expense->period_months,
                'monthly' => round($expense->monthlyAmount(), 3),
            ]);
    }

    public function fixedExpensesTotal(): float
    {
        return (float) DB::table('expenses')
            ->where('is_active', true)
            ->selectRaw('ROUND(COALESCE(SUM(amount * 1.0 / CASE WHEN period_months > 0 THEN period_months ELSE 1 END), 0), 3) AS total')
            ->value('total');
    }

    public function netProfit(): float
    {
        return round($this->totalSales() - $this->purchasesTotal() - $this->fixedExpensesTotal(), 3);
    }

    /**
     * Stock in/out per ingredient over the month, from the movement ledger.
     * Movements tied to cancelled orders (the sale and its reversal) are left out.
     *
     * @return Collection<int, object{ingredient_id: int, name: string, unit: string, in: float, out: float, net: float}>
     */
    public function stockMovements(): Collection
    {
        return DB::table('stock_movements')
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->leftJoin('orders', 'orders.id', '=', 'stock_movements.order_id')
            ->whereNull('orders.cancelled_at')
            ->where('stock_movements.created_at', '>=', $this->start)
            ->where('stock_movements.created_at', '<', $this->end)
            ->groupBy('ingredients.id', 'ingredients.name', 'ingredients.unit')
            ->orderBy('ingredients.name')
            ->selectRaw('ingredients.id AS ingredient_id, ingredients.name, ingredients.unit')
            ->selectRaw('ROUND(SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END), 3) AS qty_in')
            ->selectRaw('ROUND(SUM(CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.quantity ELSE 0 END), 3) AS qty_out')
            ->selectRaw('ROUND(SUM(stock_movements.quantity), 3) AS qty_net')
            ->get()
            ->map(fn (object $row) => (object) [
                'ingredient_id' => (int) $row->ingredient_id,
                'name' => $row->name,
                'unit' => $row->unit,
                'in' => (float) $row->qty_in,
                'out' => (float) $row->qty_out,
                'net' => (float) $row->qty_net,
            ]);
    }
}
