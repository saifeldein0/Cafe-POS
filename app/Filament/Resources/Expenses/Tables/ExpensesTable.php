<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Models\Expense;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->sortable(),
                TextColumn::make('period_months')
                    ->label(__('Period'))
                    ->formatStateUsing(fn ($state): string => ExpenseForm::periodLabel($state)),
                TextColumn::make('monthly')
                    ->label(__('Per month'))
                    ->state(fn (Expense $record): float => round($record->monthlyAmount(), 3))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT'),
                ToggleColumn::make('is_active')
                    ->label(__('Enabled')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
