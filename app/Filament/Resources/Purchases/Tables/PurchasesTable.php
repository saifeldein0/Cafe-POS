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
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ingredient.name')
                    ->label('Ingrédient')
                    ->searchable(),
                TextColumn::make('packages')
                    ->label('Cond.')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' '),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(fn (Purchase $record): string => ' '.$record->ingredient->unit),
                TextColumn::make('total_cost')
                    ->label('Coût total')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                            ->suffix(' DT'),
                    ),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Mois')
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
                    ->modalDescription('Le stock de l\'ingrédient sera diminué de la quantité achetée.')
                    ->using(function (Purchase $record): bool {
                        app(PurchaseService::class)->delete($record);

                        return true;
                    }),
            ]);
    }
}
