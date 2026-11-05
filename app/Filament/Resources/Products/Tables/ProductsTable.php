<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->sortable(),
                TextColumn::make('estimated_cost')
                    ->label(__('Estimated cost'))
                    ->state(fn (Product $record): ?float => $record->recipeCost())
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->placeholder('—'),
                TextColumn::make('recipe_status')
                    ->label(__('Recipe'))
                    ->state(fn (Product $record): string => $record->hasIncompleteRecipe() ? __('incomplete') : __('complete'))
                    ->badge()
                    ->color(fn (Product $record): string => $record->hasIncompleteRecipe() ? 'warning' : 'success'),
                TextColumn::make('margin')
                    ->label(__('Margin'))
                    ->state(fn (Product $record): ?float => $record->hasIncompleteRecipe()
                        ? null
                        : round((float) $record->price - (float) $record->recipeCost(), 3))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
