<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\RecipeItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class RecipeItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipeItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Recipe');
    }

    protected static function getModelLabel(): ?string
    {
        return __('ingredient');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('ingredients');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ingredient_id')
                    ->label(__('Ingredient'))
                    ->relationship('ingredient', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()),
                    ),
                TextInput::make('quantity')
                    ->label(__('Quantity'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->placeholder(__('unknown'))
                    ->helperText(__('Leave empty if the quantity is unknown.')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('ingredient'))
            ->columns([
                TextColumn::make('ingredient.name')
                    ->label(__('Ingredient')),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->formatStateUsing(fn ($state, RecipeItem $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.').' '.$record->ingredient->unitLabel())
                    ->placeholder(__('unknown'))
                    ->color(fn (RecipeItem $record) => $record->quantity === null ? 'warning' : null),
                TextColumn::make('line_cost')
                    ->label(__('Cost'))
                    ->state(fn (RecipeItem $record): ?float => $record->quantity === null
                        ? null
                        : round((float) $record->quantity * (float) $record->ingredient->last_unit_cost, 3))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
