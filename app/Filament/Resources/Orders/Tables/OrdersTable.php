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
                    ->label(__('No.'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                    ->suffix(' DT')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label(__('Items'))
                    ->numeric(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (Order $record): ?string => $record->isCancelled() ? __('Cancelled') : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
            ])
            ->recordClasses(fn (Order $record): ?string => $record->isCancelled() ? 'opacity-60' : null)
            ->filters([
                Filter::make('created_at')
                    ->label(__('Period'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('To')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<', Carbon::parse($date)->addDay()->startOfDay())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = __('From :date', ['date' => Carbon::parse($data['from'])->format('d/m/Y')]);
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = __('To :date', ['date' => Carbon::parse($data['until'])->format('d/m/Y')]);
                        }

                        return $indicators;
                    }),
                TernaryFilter::make('cancelled_at')
                    ->label(__('Status'))
                    ->nullable()
                    ->placeholder(__('All orders'))
                    ->trueLabel(__('Cancelled only'))
                    ->falseLabel(__('Valid only')),
            ])
            ->recordActions([
                ViewAction::make(),
                CancelOrderAction::make(),
            ]);
    }
}
