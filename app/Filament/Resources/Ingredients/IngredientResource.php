<?php

namespace App\Filament\Resources\Ingredients;

use App\Filament\Resources\Ingredients\Pages\CreateIngredient;
use App\Filament\Resources\Ingredients\Pages\EditIngredient;
use App\Filament\Resources\Ingredients\Pages\ListIngredients;
use App\Filament\Resources\Ingredients\Schemas\IngredientForm;
use App\Filament\Resources\Ingredients\Tables\IngredientsTable;
use App\Models\Ingredient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IngredientResource extends Resource
{
    protected static ?string $model = Ingredient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('ingredient');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ingredients');
    }

    public static function getNavigationLabel(): string
    {
        return __('Stock');
    }

    public static function form(Schema $schema): Schema
    {
        return IngredientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngredientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIngredients::route('/'),
            'create' => CreateIngredient::route('/create'),
            'edit' => EditIngredient::route('/{record}/edit'),
        ];
    }
}
