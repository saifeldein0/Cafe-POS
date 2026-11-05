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
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Prix')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->sortable(),
                TextColumn::make('estimated_cost')
                    ->label('Coût estimé')
                    ->state(fn (Product $record): ?float => $record->recipeCost())
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->placeholder('—'),
                TextColumn::make('recipe_status')
                    ->label('Recette')
                    ->state(fn (Product $record): string => $record->hasIncompleteRecipe() ? 'incomplet' : 'complète')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'incomplet' ? 'warning' : 'success'),
                TextColumn::make('margin')
                    ->label('Marge')
                    ->state(fn (Product $record): ?float => $record->hasIncompleteRecipe()
                        ? null
                        : round((float) $record->price - (float) $record->recipeCost(), 3))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
