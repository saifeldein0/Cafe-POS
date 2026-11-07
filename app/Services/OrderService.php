<?php

namespace App\Services;

use App\Exceptions\NoOpenShiftException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    /**
     * Create an order from a cart of [product_id => quantity].
     *
     * Lines with a non-positive quantity are ignored. Unknown or inactive products are
     * rejected. Stock is deducted per known recipe line and may go negative: a sale is
     * never blocked by stock.
     *
     * The order belongs to the open shift; with no shift open, nothing is recorded.
     *
     * @param  array<int|string, int|string>  $cart
     *
     * @throws InvalidArgumentException
     * @throws NoOpenShiftException
     */
    public function create(array $cart): Order
    {
        $lines = [];
        foreach ($cart as $productId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity > 0) {
                $lines[(int) $productId] = ($lines[(int) $productId] ?? 0) + $quantity;
            }
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Le panier est vide.');
        }

        $products = Product::query()
            ->active()
            ->whereKey(array_keys($lines))
            ->with('recipeItems')
            ->get()
            ->keyBy('id');

        $unavailable = array_diff(array_keys($lines), $products->keys()->all());
        if ($unavailable !== []) {
            throw new InvalidArgumentException('Produit introuvable ou inactif : '.implode(', ', $unavailable));
        }

        return DB::transaction(function () use ($lines, $products) {
            $shift = Shift::current() ?? throw new NoOpenShiftException;

            $order = Order::create(['total' => 0, 'shift_id' => $shift->id]);

            $total = 0.0;
            $deductions = [];

            foreach ($lines as $productId => $quantity) {
                $product = $products[$productId];
                $lineTotal = round((float) $product->price * $quantity, 3);
                $total += $lineTotal;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);

                foreach ($product->recipeItems as $recipeItem) {
                    if ($recipeItem->quantity === null) {
                        continue; // unknown quantity: deduct nothing
                    }

                    $deductions[$recipeItem->ingredient_id] = ($deductions[$recipeItem->ingredient_id] ?? 0)
                        + (float) $recipeItem->quantity * $quantity;
                }
            }

            foreach ($deductions as $ingredientId => $amount) {
                $this->moveStock($ingredientId, -$amount, StockMovement::REASON_SALE, $order->id);
            }

            $order->update(['total' => round($total, 3)]);

            return $order->load('items');
        });
    }

    /**
     * Void an order: mark it cancelled and reverse its sale movements. Idempotent.
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->isCancelled()) {
                $order->setRawAttributes($fresh->getAttributes(), true);

                return;
            }

            $sales = $fresh->stockMovements()
                ->where('reason', StockMovement::REASON_SALE)
                ->get();

            foreach ($sales as $movement) {
                $this->moveStock($movement->ingredient_id, -(float) $movement->quantity, StockMovement::REASON_CANCEL, $fresh->id);
            }

            $fresh->update(['cancelled_at' => now()]);
            $order->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    private function moveStock(int $ingredientId, float $quantity, string $reason, int $orderId): void
    {
        $quantity = round($quantity, 3);

        StockMovement::create([
            'ingredient_id' => $ingredientId,
            'quantity' => $quantity,
            'reason' => $reason,
            'order_id' => $orderId,
        ]);

        Ingredient::whereKey($ingredientId)->increment('current_stock', $quantity);
    }
}
