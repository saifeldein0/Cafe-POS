<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\Actions\EndShiftAction;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Shift $record
 */
class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    public function getTitle(): string
    {
        return __('Shift of :date', ['date' => $this->record->started_at->format('d/m/Y H:i')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EndShiftAction::make(),
        ];
    }
}
