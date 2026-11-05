<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use App\Models\Order;
use App\Services\MonthlyReport;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $todaySales = (float) Order::query()
            ->notCancelled()
            ->where('created_at', '>=', now()->startOfDay())
            ->where('created_at', '<', now()->addDay()->startOfDay())
            ->sum('total');

        $report = MonthlyReport::current();
        $monthSales = $report->totalSales();
        $netProfit = $report->netProfit();

        $outOfStock = Ingredient::query()->where('current_stock', '<=', 0)->count();

        return [
            Stat::make('Ventes du jour', Money::format($todaySales)),
            Stat::make('Ventes du mois', Money::format($monthSales)),
            Stat::make('Bénéfice net estimé (mois)', Money::format($netProfit))
                ->description('Ventes − achats − charges fixes')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
            Stat::make('Ingrédients en rupture', (string) $outOfStock)
                ->description('Stock nul ou négatif')
                ->color($outOfStock > 0 ? 'danger' : 'success'),
        ];
    }
}
