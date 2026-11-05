<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('No.')),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('total')
                            ->label(__('Total'))
                            ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                            ->suffix(' DT')
                            ->weight('bold'),
                        TextEntry::make('cancelled_at')
                            ->label(__('Cancelled on'))
                            ->dateTime('d/m/Y H:i')
                            ->badge()
                            ->color('danger')
                            ->placeholder('—')
                            ->visible(fn (Order $record): bool => $record->isCancelled()),
                    ]),
                Section::make(__('Items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                RepeatableEntry\TableColumn::make(__('Product')),
                                RepeatableEntry\TableColumn::make(__('Unit price')),
                                RepeatableEntry\TableColumn::make(__('Qty')),
                                RepeatableEntry\TableColumn::make(__('Total')),
                            ])
                            ->schema([
                                TextEntry::make('product_name'),
                                TextEntry::make('unit_price')
                                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                                    ->suffix(' DT'),
                                TextEntry::make('quantity'),
                                TextEntry::make('line_total')
                                    ->numeric(decimalPlaces: 3, decimalSeparator: '.', thousandsSeparator: ' ')
                                    ->suffix(' DT'),
                            ]),
                    ]),
            ]);
    }
}
