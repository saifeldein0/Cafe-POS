<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use App\Models\Ingredient;
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
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('unit')
                    ->label(__('Unit'))
                    ->options(Ingredient::unitOptions())
                    ->required(),
                TextInput::make('current_stock')
                    ->label(fn (string $operation): string => $operation === 'create' ? __('Initial stock') : __('Current stock'))
                    ->helperText(__('To correct the stock, use “Adjust stock” (keeps a record).'))
                    ->numeric()
                    ->step(0.001)
                    ->default(0)
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('package_name')
                    ->label(__('Package'))
                    ->placeholder(__('e.g. Box of 12'))
                    ->maxLength(255),
                TextInput::make('units_per_package')
                    ->label(__('Units per package'))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->default(1)
                    ->required(),
                TextInput::make('last_unit_cost')
                    ->label(__('Last unit cost'))
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->suffix('DT'),
            ]);
    }
}
