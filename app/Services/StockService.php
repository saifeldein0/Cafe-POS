<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Manually correct an ingredient's stock (inventory count, loss, breakage...).
     * The signed quantity is recorded as an `adjustment` movement.
     */
    public function adjust(Ingredient $ingredient, float $quantity, ?string $note = null): StockMovement
    {
        $quantity = round($quantity, 3);

        return DB::transaction(function () use ($ingredient, $quantity, $note) {
            $movement = StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'quantity' => $quantity,
                'reason' => StockMovement::REASON_ADJUSTMENT,
                'note' => filled($note) ? $note : null,
            ]);

            Ingredient::whereKey($ingredient->id)->increment('current_stock', $quantity);
            $ingredient->refresh();

            return $movement;
        });
    }
}
