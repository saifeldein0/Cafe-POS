<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public const PERIODS = [
        1 => 'par mois',
        3 => 'par 3 mois',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0)
                    ->required()
                    ->suffix('DT'),
                Select::make('period_months')
                    ->label('Période')
                    ->options(self::PERIODS)
                    ->default(1)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
