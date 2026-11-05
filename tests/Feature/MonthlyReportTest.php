<?php

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\MonthlyReport;
use App\Services\OrderService;
use App\Services\PurchaseService;
use Database\Seeders\DatabaseSeeder;

/**
 * Known month: October 2026.
 *  - Order A: 2 × Express (1.500) + 1 × Croissant (1.200) = 4.200
 *  - Order B: 1 × Mojito (6.000, no recipe)                = 6.000
 *  - Order C: 1 × Express, cancelled                      → excluded
 *  - Purchase: 1 paquet Café (1000 g) for 30.000
 * Noise outside the month: a September order + purchase, and a 1 November order.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    openShift();
    $orders = app(OrderService::class);
    $purchases = app(PurchaseService::class);
    $id = fn (string $name): int => Product::where('name', $name)->value('id');
    $ingredientId = fn (string $name): int => Ingredient::where('name', $name)->value('id');

    // Prior month (excluded)
    $this->travelTo('2026-09-20 18:00:00');
    $orders->create([$id('Express') => 3]);
    $purchases->create(['ingredient_id' => $ingredientId('Sucre'), 'packages' => 10, 'total_cost' => 0.500, 'purchased_at' => '2026-09-20']);

    // Report month
    $this->travelTo('2026-10-05 09:00:00');
    $purchases->create(['ingredient_id' => $ingredientId('Café'), 'packages' => 1, 'total_cost' => 30.000, 'purchased_at' => '2026-10-05']);

    $this->travelTo('2026-10-10 10:00:00');
    $orders->create([$id('Express') => 2, $id('Croissant') => 1]);
    $orders->create([$id('Mojito') => 1]);
    $cancelled = $orders->create([$id('Express') => 1]);
    $orders->cancel($cancelled);

    // Next month (excluded)
    $this->travelTo('2026-11-01 00:00:00');
    $orders->create([$id('Gâteau') => 4]);

    $this->report = MonthlyReport::for(2026, 10);
});

it('reports per-product quantity and revenue for the month only', function () {
    $rows = $this->report->productSales()->keyBy('product_name');

    expect($rows->keys()->sort()->values()->all())->toBe(['Croissant', 'Express', 'Mojito'])
        ->and($rows['Express']->quantity)->toBe(2)
        ->and($rows['Express']->revenue)->toBe(3.0)
        ->and($rows['Croissant']->quantity)->toBe(1)
        ->and($rows['Croissant']->revenue)->toBe(1.2)
        ->and($rows['Mojito']->quantity)->toBe(1)
        ->and($rows['Mojito']->revenue)->toBe(6.0);
});

it('estimates product cost and flags incomplete recipes', function () {
    $rows = $this->report->productSales()->keyBy('product_name');

    // Express = 9 g café × 0.030 + 5 g sucre × 0.010 = 0.320 per unit
    expect($rows['Express']->estimated_cost)->toBe(0.64)
        ->and($rows['Express']->margin)->toBe(2.36)
        ->and($rows['Express']->incomplete)->toBeFalse()
        ->and($rows['Croissant']->estimated_cost)->toBe(0.7)
        ->and($rows['Mojito']->estimated_cost)->toBe(0.0)
        ->and($rows['Mojito']->incomplete)->toBeTrue();
});

it('computes month totals with cancelled and out-of-month data excluded', function () {
    expect($this->report->totalSales())->toBe(10.2)
        ->and($this->report->purchasesTotal())->toBe(30.0);
});

it('prorates fixed expenses, with beIN at 56.667 per month', function () {
    $lines = $this->report->fixedExpenseLines()->keyBy('name');

    expect($lines['beIN Sports']->monthly)->toBe(56.667)
        ->and($lines['Loyer']->monthly)->toBe(1200.0)
        // 1200 + 350 + 60 + 170/3 + 1800
        ->and($this->report->fixedExpensesTotal())->toBe(3466.667);
});

it('ignores inactive expenses', function () {
    Expense::where('name', 'Internet')->update(['is_active' => false]);

    expect($this->report->fixedExpensesTotal())->toBe(3406.667);
});

it('computes net profit as sales minus purchases minus fixed expenses', function () {
    // 10.200 − 30.000 − 3466.667
    expect($this->report->netProfit())->toBe(-3486.467);
});

it('summarises stock in and out per ingredient, without cancelled orders', function () {
    $stock = $this->report->stockMovements()->keyBy('name');

    expect($stock->keys()->sort()->values()->all())->toBe(['Café', 'Croissant', 'Sucre'])
        ->and($stock['Café']->in)->toBe(1000.0)
        ->and($stock['Café']->out)->toBe(18.0)
        ->and($stock['Café']->net)->toBe(982.0)
        ->and($stock['Sucre']->in)->toBe(0.0)
        ->and($stock['Sucre']->out)->toBe(10.0)
        ->and($stock['Croissant']->out)->toBe(1.0);
});

it('returns zeros for an empty month', function () {
    $report = MonthlyReport::for(2026, 1);

    expect($report->totalSales())->toBe(0.0)
        ->and($report->purchasesTotal())->toBe(0.0)
        ->and($report->productSales())->toBeEmpty()
        ->and($report->stockMovements())->toBeEmpty()
        ->and($report->netProfit())->toBe(-3466.667);
});
