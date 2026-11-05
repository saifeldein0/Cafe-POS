<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('unit')
                    ->label('Unité')
                    ->options(['g' => 'g', 'ml' => 'ml', 'piece' => 'pièce'])
                    ->required(),
                TextInput::make('current_stock')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Stock initial' : 'Stock actuel')
                    ->helperText('Pour corriger le stock, utilisez « Ajuster le stock » (garde une trace).')
                    ->numeric()
                    ->step(0.001)
                    ->default(0)
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('package_name')
                    ->label('Conditionnement')
                    ->placeholder('ex. Carton 12')
                    ->maxLength(255),
                TextInput::make('units_per_package')
                    ->label('Unités par conditionnement')
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->default(1)
                    ->required(),
                TextInput::make('last_unit_cost')
                    ->label('Dernier coût unitaire')
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->suffix('DT'),
            ]);
    }
}
