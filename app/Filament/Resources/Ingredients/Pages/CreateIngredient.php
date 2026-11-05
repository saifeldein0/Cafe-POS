<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\IngredientResource;
use App\Models\Ingredient;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateIngredient extends CreateRecord
{
    protected static string $resource = IngredientResource::class;

    /**
     * The initial stock goes through the ledger as an adjustment, like any other stock change.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $initialStock = (float) ($data['current_stock'] ?? 0);
        $data['current_stock'] = 0;

        return DB::transaction(function () use ($data, $initialStock) {
            $ingredient = Ingredient::create($data);

            if ($initialStock != 0) {
                app(StockService::class)->adjust($ingredient, $initialStock, 'Stock initial');
            }

            return $ingredient;
        });
    }
}
