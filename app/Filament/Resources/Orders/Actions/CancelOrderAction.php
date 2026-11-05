<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Annuler')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => "Annuler la commande n° {$record->id} ?")
            ->modalDescription('La commande sera exclue des ventes et le stock des ingrédients sera restauré.')
            ->modalSubmitActionLabel('Oui, annuler')
            ->hidden(fn (Order $record): bool => $record->isCancelled())
            ->action(function (Order $record): void {
                app(OrderService::class)->cancel($record);

                Notification::make()->title('Commande annulée')->success()->send();
            });
    }
}
