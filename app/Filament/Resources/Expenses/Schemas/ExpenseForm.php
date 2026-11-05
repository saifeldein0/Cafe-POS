<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public const PERIODS = [1, 3];

    /**
     * @return array<int, string>
     */
    public static function periodOptions(): array
    {
        return collect(self::PERIODS)
            ->mapWithKeys(fn (int $months): array => [$months => self::periodLabel($months)])
            ->all();
    }

    public static function periodLabel(int|string|null $months): string
    {
        return (int) $months === 1
            ? __('per month')
            : __('every :count months', ['count' => (int) $months]);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0)
                    ->required()
                    ->suffix('DT'),
                Select::make('period_months')
                    ->label(__('Period'))
                    ->options(self::periodOptions())
                    ->default(1)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('Enabled'))
                    ->default(true),
            ]);
    }
}
