<?php

namespace App\Filament\Resources\Ingredients\Tables;

use App\Filament\Resources\Ingredients\Actions\AdjustStockAction;
use App\Models\Ingredient;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Ingredient $record): string => ' '.$record->unit)
                    ->color(fn (Ingredient $record): ?string => (float) $record->current_stock <= 0 ? 'danger' : null)
                    ->weight(fn (Ingredient $record): ?string => (float) $record->current_stock <= 0 ? 'bold' : null)
                    ->sortable(),
                TextColumn::make('last_unit_cost')
                    ->label('Coût unitaire')
                    ->numeric(decimalPlaces: 4, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Ingredient $record): string => ' DT/'.$record->unit)
                    ->sortable(),
                TextColumn::make('package_name')
                    ->label('Conditionnement')
                    ->description(fn (Ingredient $record): string => self::trimDecimal($record->units_per_package).' '.$record->unit.' / conditionnement')
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('out_of_stock')
                    ->label('Stock nul ou négatif')
                    ->query(fn (Builder $query) => $query->where('current_stock', '<=', 0)),
            ])
            ->recordActions([
                AdjustStockAction::make(),
                EditAction::make(),
            ]);
    }

    private static function trimDecimal(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }
}
