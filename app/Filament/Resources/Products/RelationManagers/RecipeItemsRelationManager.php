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
use Illuminate\Validation\Rules\Unique;

class RecipeItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipeItems';

    protected static ?string $title = 'Recette';

    protected static ?string $modelLabel = 'ingrédient';

    protected static ?string $pluralModelLabel = 'ingrédients';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ingredient_id')
                    ->label('Ingrédient')
                    ->relationship('ingredient', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()),
                    ),
                TextInput::make('quantity')
                    ->label('Quantité')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->placeholder('inconnu')
                    ->helperText('Laisser vide si la quantité est inconnue.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('ingredient'))
            ->columns([
                TextColumn::make('ingredient.name')
                    ->label('Ingrédient'),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->formatStateUsing(fn ($state, RecipeItem $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.').' '.$record->ingredient->unit)
                    ->placeholder('inconnu')
                    ->color(fn (RecipeItem $record) => $record->quantity === null ? 'warning' : null),
                TextColumn::make('line_cost')
                    ->label('Coût')
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
