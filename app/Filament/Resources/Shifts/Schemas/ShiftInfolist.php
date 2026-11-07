<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Models\Shift;
use App\Services\ShiftService;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The same receipt as the till, computed live by ShiftService::summary().
 */
class ShiftInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.shifts.receipt')
                    ->viewData(fn (Shift $record): array => [
                        'summary' => app(ShiftService::class)->summary($record),
                    ]),
            ]);
    }
}
