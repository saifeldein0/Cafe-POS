<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Ingredient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ingredient_id')
                    ->label(__('Ingredient'))
                    ->relationship('ingredient', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::computeQuantity($get, $set)),
                TextInput::make('packages')
                    ->label(__('Number of packages'))
                    ->helperText(fn (Get $get): ?string => self::packageHint($get('ingredient_id')))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->default(1)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::computeQuantity($get, $set)),
                TextInput::make('quantity')
                    ->label(__('Quantity received'))
                    ->helperText(__('Calculated automatically (packages × units), editable.'))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->required()
                    ->suffix(fn (Get $get): ?string => Ingredient::find($get('ingredient_id'))?->unitLabel()),
                TextInput::make('total_cost')
                    ->label(__('Total cost'))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0)
                    ->required()
                    ->suffix('DT'),
                DatePicker::make('purchased_at')
                    ->label(__('Purchase date'))
                    ->default(now())
                    ->maxDate(now()->endOfDay())
                    ->required(),
                TextInput::make('note')
                    ->label(__('Note'))
                    ->maxLength(255),
            ]);
    }

    private static function computeQuantity(Get $get, Set $set): void
    {
        $ingredient = Ingredient::find($get('ingredient_id'));
        $packages = $get('packages');

        if (! $ingredient || ! is_numeric($packages)) {
            return;
        }

        $set('quantity', round((float) $packages * (float) $ingredient->units_per_package, 3));
    }

    private static function packageHint(mixed $ingredientId): ?string
    {
        $ingredient = $ingredientId ? Ingredient::find($ingredientId) : null;

        if (! $ingredient) {
            return null;
        }

        $units = rtrim(rtrim(number_format((float) $ingredient->units_per_package, 3, '.', ''), '0'), '.');

        return trim(($ingredient->package_name ?? __('Package')).' = '.$units.' '.$ingredient->unitLabel());
    }
}
