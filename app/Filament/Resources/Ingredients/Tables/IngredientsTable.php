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
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label(__('Stock'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Ingredient $record): string => ' '.$record->unitLabel())
                    ->color(fn (Ingredient $record): ?string => (float) $record->current_stock <= 0 ? 'danger' : null)
                    ->weight(fn (Ingredient $record): ?string => (float) $record->current_stock <= 0 ? 'bold' : null)
                    ->sortable(),
                TextColumn::make('last_unit_cost')
                    ->label(__('Unit cost'))
                    ->numeric(decimalPlaces: 4, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Ingredient $record): string => ' DT/'.$record->unitLabel())
                    ->sortable(),
                TextColumn::make('package_name')
                    ->label(__('Package'))
                    ->description(fn (Ingredient $record): string => __(':quantity :unit / package', [
                        'quantity' => self::trimDecimal($record->units_per_package),
                        'unit' => $record->unitLabel(),
                    ]))
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('out_of_stock')
                    ->label(__('Zero or negative stock'))
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
