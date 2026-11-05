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
            ->label(__('Cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => __('Cancel order #:id?', ['id' => $record->id]))
            ->modalDescription(__('The order will be excluded from sales and the ingredient stock will be restored.'))
            ->modalSubmitActionLabel(__('Yes, cancel'))
            ->hidden(fn (Order $record): bool => $record->isCancelled())
            ->action(function (Order $record): void {
                app(OrderService::class)->cancel($record);

                Notification::make()->title(__('Order cancelled'))->success()->send();
            });
    }
}
