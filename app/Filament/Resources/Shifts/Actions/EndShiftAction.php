<?php

namespace App\Filament\Resources\Shifts\Actions;

use App\Models\Shift;
use App\Services\ShiftService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Ends a shift left open, from the dashboard. Shown only on the open shift.
 */
class EndShiftAction
{
    public static function make(): Action
    {
        return Action::make('endShift')
            ->label(__('End shift'))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Shift $record): string => __('End the shift started at :time?', ['time' => $record->started_at->format('d/m/Y H:i')]))
            ->modalDescription(__('The till will be locked until the new shift.'))
            ->modalSubmitActionLabel(__('Yes, end the shift'))
            ->visible(fn (Shift $record): bool => $record->isOpen())
            ->action(function (Shift $record): void {
                app(ShiftService::class)->end($record);

                Notification::make()->title(__('Shift ended'))->success()->send();
            });
    }
}
