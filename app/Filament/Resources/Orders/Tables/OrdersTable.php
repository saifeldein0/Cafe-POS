<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Actions\CancelOrderAction;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('items as items_count', 'quantity'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Articles')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->state(fn (Order $record): ?string => $record->isCancelled() ? 'Annulée' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
            ])
            ->recordClasses(fn (Order $record): ?string => $record->isCancelled() ? 'opacity-60' : null)
            ->filters([
                Filter::make('created_at')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<', Carbon::parse($date)->addDay()->startOfDay())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Du '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Au '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                TernaryFilter::make('cancelled_at')
                    ->label('Statut')
                    ->nullable()
                    ->placeholder('Toutes')
                    ->trueLabel('Annulées')
                    ->falseLabel('Valides'),
            ])
            ->recordActions([
                ViewAction::make(),
                CancelOrderAction::make(),
            ]);
    }
}
