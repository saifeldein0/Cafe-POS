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
            ->label(__('Adjust stock'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalHeading(fn (Ingredient $record): string => __('Adjust stock: :name', ['name' => $record->name]))
            ->modalDescription(__('Positive quantity to add, negative to remove (breakage, loss, stock count).'))
            ->schema([
                TextInput::make('quantity')
                    ->label(__('Quantity (+/−)'))
                    ->numeric()
                    ->step(0.001)
                    ->required()
                    ->notIn(['0'])
                    ->suffix(fn (Ingredient $record): string => $record->unitLabel()),
                TextInput::make('note')
                    ->label(__('Note'))
                    ->maxLength(255),
            ])
            ->action(function (Ingredient $record, array $data): void {
                app(StockService::class)->adjust($record, (float) $data['quantity'], $data['note'] ?? null);

                Notification::make()
                    ->title(__('Stock adjusted'))
                    ->body(__('New stock: :stock :unit', ['stock' => $record->current_stock, 'unit' => $record->unitLabel()]))
                    ->success()
                    ->send();
            });
    }
}
