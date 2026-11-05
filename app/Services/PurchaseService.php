<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * Record a purchase of an ingredient.
     *
     * Expected keys: ingredient_id, packages, total_cost, purchased_at, and optionally
     * quantity (defaults to packages × units_per_package) and note.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($data['ingredient_id']);

            $packages = (float) ($data['packages'] ?? 0);
            $quantity = isset($data['quantity']) && $data['quantity'] !== ''
                ? (float) $data['quantity']
                : $packages * (float) $ingredient->units_per_package;
            $quantity = round($quantity, 3);
            $totalCost = round((float) $data['total_cost'], 3);

            $purchase = Purchase::create([
                'ingredient_id' => $ingredient->id,
                'packages' => $packages,
                'quantity' => $quantity,
                'total_cost' => $totalCost,
                'purchased_at' => $data['purchased_at'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
            ]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'quantity' => $quantity,
                'reason' => StockMovement::REASON_PURCHASE,
                'purchase_id' => $purchase->id,
            ]);

            $ingredient->increment('current_stock', $quantity);

            if ($quantity > 0) {
                $ingredient->update(['last_unit_cost' => round($totalCost / $quantity, 4)]);
            }

            return $purchase;
        });
    }

    /**
     * Delete a mis-entered purchase and reverse its stock. The ingredient's
     * last_unit_cost is left as is (the owner can edit it).
     */
    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $movements = $purchase->stockMovements()->get();

            foreach ($movements as $movement) {
                Ingredient::whereKey($movement->ingredient_id)
                    ->decrement('current_stock', (float) $movement->quantity);
                $movement->delete();
            }

            $purchase->delete();
        });
    }
}
