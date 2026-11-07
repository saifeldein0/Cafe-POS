<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Filament\Resources\Shifts\Actions\EndShiftAction;
use App\Models\Shift;
use App\Support\Duration;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::withTotals($query))
            ->defaultSort('started_at', 'desc')
            ->columns(static::columns())
            ->filters([
                Filter::make('started_at')
                    ->label(__('Period'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('To')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('started_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('started_at', '<', Carbon::parse($date)->addDay()->startOfDay())))
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
            ])
            ->recordActions([
                ViewAction::make(),
                EndShiftAction::make(),
            ]);
    }

    /**
     * Valid (not cancelled) orders count and total per shift, in the same query.
     */
    public static function withTotals(Builder $query): Builder
    {
        return $query
            ->withCount(['orders as valid_orders_count' => fn (Builder $q) => $q->whereNull('cancelled_at')])
            ->withSum(['orders as valid_total' => fn (Builder $q) => $q->whereNull('cancelled_at')], 'total');
    }

    /**
     * Shared with the dashboard's "last shifts" widget.
     *
     * @return array<TextColumn>
     */
    public static function columns(bool $sortable = true): array
    {
        return [
            TextColumn::make('started_at')
                ->label(__('Started'))
                ->dateTime('d/m/Y H:i')
                ->sortable($sortable),
            TextColumn::make('ended_at')
                ->label(__('Ended'))
                ->state(fn (Shift $record): string => $record->ended_at?->format('d/m/Y H:i') ?? __('In progress'))
                ->badge(fn (Shift $record): bool => $record->isOpen())
                ->color(fn (Shift $record): ?string => $record->isOpen() ? 'success' : null)
                ->sortable($sortable),
            TextColumn::make('duration')
                ->label(__('Duration'))
                ->state(fn (Shift $record): string => Duration::between($record->started_at, $record->ended_at)),
            TextColumn::make('valid_orders_count')
                ->label(__('Valid orders'))
                ->numeric(),
            TextColumn::make('valid_total')
                ->label(__('Total'))
                ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                ->suffix(' DT')
                ->default(0)
                ->weight('semibold'),
        ];
    }
}
