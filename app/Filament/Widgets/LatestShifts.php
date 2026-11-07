<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Shifts\Pages\ViewShift;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\Shifts\Tables\ShiftsTable;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dashboard: the last five shifts, each opening its receipt.
 */
class LatestShifts extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Last shifts'))
            ->query(fn () => ShiftsTable::withTotals(Shift::query())->latest('started_at')->latest('id')->limit(5))
            ->columns(ShiftsTable::columns(sortable: false))
            ->paginated(false)
            ->recordUrl(fn (Shift $record): string => ViewShift::getUrl(['record' => $record]))
            ->headerActions([
                Action::make('history')
                    ->label(__('Shift history'))
                    ->link()
                    ->url(ShiftResource::getUrl('index')),
            ])
            ->emptyStateHeading(__('No shifts yet'));
    }
}
