<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Support\Months;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('ingredient'))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('purchased_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('purchased_at')
                    ->label(__('Date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ingredient.name')
                    ->label(__('Ingredient'))
                    ->searchable(),
                TextColumn::make('packages')
                    ->label(__('Pkg.'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' '),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Purchase $record): string => ' '.$record->ingredient->unitLabel()),
                TextColumn::make('total_cost')
                    ->label(__('Total cost'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->summarize(
                        Sum::make()
                            ->label(__('Total'))
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                            ->suffix(' DT'),
                    ),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label(__('Month'))
                    ->options(Months::options())
                    ->default(now()->format('Y-m'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $start = Months::parse($data['value']);

                        return $query
                            ->where('purchased_at', '>=', $start->toDateString())
                            ->where('purchased_at', '<', $start->addMonth()->toDateString());
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription(__('The ingredient stock will be reduced by the purchased quantity.'))
                    ->using(function (Purchase $record): bool {
                        app(PurchaseService::class)->delete($record);

                        return true;
                    }),
            ]);
    }
}
