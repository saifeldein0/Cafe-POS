<?php

namespace App\Filament\Resources\Ingredients\Actions;

use App\Models\Ingredient;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class AdjustStockAction
{
    public static function make(): Action
    {
        return Action::make('adjustStock')
            ->label('Ajuster le stock')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalHeading(fn (Ingredient $record): string => "Ajuster le stock : {$record->name}")
            ->modalDescription('Quantité positive pour ajouter, négative pour retirer (casse, perte, inventaire).')
            ->schema([
                TextInput::make('quantity')
                    ->label('Quantité (+/−)')
                    ->numeric()
                    ->step(0.001)
                    ->required()
                    ->notIn(['0'])
                    ->suffix(fn (Ingredient $record): string => $record->unit),
                TextInput::make('note')
                    ->label('Note')
                    ->maxLength(255),
            ])
            ->action(function (Ingredient $record, array $data): void {
                app(StockService::class)->adjust($record, (float) $data['quantity'], $data['note'] ?? null);

                Notification::make()
                    ->title('Stock ajusté')
                    ->body("Nouveau stock : {$record->current_stock} {$record->unit}")
                    ->success()
                    ->send();
            });
    }
}
