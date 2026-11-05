<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\Actions\AdjustStockAction;
use App\Filament\Resources\Ingredients\IngredientResource;
use App\Models\Ingredient;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIngredient extends EditRecord
{
    protected static string $resource = IngredientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdjustStockAction::make()
                ->after(fn () => $this->refreshFormData(['current_stock'])),
            // Ingredients used in a recipe or purchase history cannot be deleted (FK restrict).
            DeleteAction::make()
                ->hidden(fn (Ingredient $record): bool => $record->recipeItems()->exists() || $record->purchases()->exists()),
        ];
    }
}
