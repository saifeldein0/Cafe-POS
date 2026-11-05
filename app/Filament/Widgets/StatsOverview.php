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
            Stat::make(__('Sales today'), Money::format($todaySales)),
            Stat::make(__('Sales this month'), Money::format($monthSales)),
            Stat::make(__('Estimated net profit (month)'), Money::format($netProfit))
                ->description(__('Sales − purchases − fixed expenses'))
                ->color($netProfit >= 0 ? 'success' : 'danger'),
            Stat::make(__('Out-of-stock ingredients'), (string) $outOfStock)
                ->description(__('Zero or negative stock'))
                ->color($outOfStock > 0 ? 'danger' : 'success'),
        ];
    }
}
