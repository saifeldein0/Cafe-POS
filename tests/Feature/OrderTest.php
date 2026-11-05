<?php

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\OrderService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->service = app(OrderService::class);
    openShift();
});

function product(string $name): Product
{
    return Product::where('name', $name)->firstOrFail();
}

function ingredient(string $name): Ingredient
{
    return Ingredient::where('name', $name)->firstOrFail();
}

it('creates an order with snapshot items and correct total', function () {
    $express = product('Express');
    $croissant = product('Croissant');

    $order = $this->service->create([$express->id => 2, $croissant->id => 1]);

    expect((float) $order->total)->toBe(4.2)
        ->and($order->items)->toHaveCount(2);

    $line = $order->items->firstWhere('product_id', $express->id);
    expect($line->product_name)->toBe('Express')
        ->and((float) $line->unit_price)->toBe(1.5)
        ->and($line->quantity)->toBe(2)
        ->and((float) $line->line_total)->toBe(3.0);

    // Changing the product afterwards does not alter the snapshot
    $express->update(['name' => 'Express Renamed', 'price' => 9.999]);
    $line->refresh();
    expect($line->product_name)->toBe('Express')
        ->and((float) $line->unit_price)->toBe(1.5);
});

it('deducts 9g of café and 5g of sucre per Express', function () {
    $cafeBefore = (float) ingredient('Café')->current_stock;
    $sucreBefore = (float) ingredient('Sucre')->current_stock;

    $order = $this->service->create([product('Express')->id => 1]);

    expect((float) ingredient('Café')->current_stock)->toBe($cafeBefore - 9)
        ->and((float) ingredient('Sucre')->current_stock)->toBe($sucreBefore - 5);

    $movements = StockMovement::where('order_id', $order->id)->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->pluck('reason')->unique()->all())->toBe(['sale'])
        ->and((float) $movements->firstWhere('ingredient_id', ingredient('Café')->id)->quantity)->toBe(-9.0)
        ->and((float) $movements->firstWhere('ingredient_id', ingredient('Sucre')->id)->quantity)->toBe(-5.0);
});

it('multiplies deductions by the ordered quantity', function () {
    $this->service->create([product('Express')->id => 3]);

    expect((float) ingredient('Café')->current_stock)->toBe(-27.0);
});

it('deducts nothing for recipe lines with unknown quantity', function () {
    $order = $this->service->create([product('Chocolat au Lait')->id => 1]);

    expect(StockMovement::where('order_id', $order->id)->count())->toBe(0)
        ->and((float) ingredient('Lait')->current_stock)->toBe(0.0);
});

it('deducts known lines but skips unknown milk for a Cappucine', function () {
    $order = $this->service->create([product('Cappucine')->id => 1]);

    expect(StockMovement::where('order_id', $order->id)->count())->toBe(2)
        ->and((float) ingredient('Lait')->current_stock)->toBe(0.0)
        ->and((float) ingredient('Café')->current_stock)->toBe(-9.0);
});

it('allows stock to go negative without blocking the sale', function () {
    expect((float) ingredient('Croissant')->current_stock)->toBe(0.0);

    $order = $this->service->create([product('Croissant')->id => 2]);

    expect($order->exists)->toBeTrue()
        ->and((float) ingredient('Croissant')->current_stock)->toBe(-2.0);
});

it('rejects an empty cart', function () {
    $this->service->create([]);
})->throws(InvalidArgumentException::class);

it('rejects inactive or missing products', function () {
    $express = product('Express');
    $express->update(['is_active' => false]);

    expect(fn () => $this->service->create([$express->id => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->service->create([999999 => 1]))->toThrow(InvalidArgumentException::class)
        ->and(Order::count())->toBe(0);
});

it('ignores cart lines with a non-positive quantity', function () {
    $order = $this->service->create([product('Express')->id => 1, product('Croissant')->id => 0]);

    expect($order->items)->toHaveCount(1)
        ->and((float) $order->total)->toBe(1.5);
});

it('cancels an order and restores stock', function () {
    $order = $this->service->create([product('Express')->id => 2]);

    $this->service->cancel($order);

    expect($order->fresh()->cancelled_at)->not->toBeNull()
        ->and((float) ingredient('Café')->current_stock)->toBe(0.0)
        ->and((float) ingredient('Sucre')->current_stock)->toBe(0.0);

    $cancel = StockMovement::where('order_id', $order->id)->where('reason', 'cancel')->get();
    expect($cancel)->toHaveCount(2)
        ->and((float) $cancel->firstWhere('ingredient_id', ingredient('Café')->id)->quantity)->toBe(18.0);

    expect(Order::notCancelled()->count())->toBe(0);
});

it('cancelling twice is idempotent', function () {
    $order = $this->service->create([product('Express')->id => 1]);

    $this->service->cancel($order);
    $this->service->cancel($order->fresh());

    expect(StockMovement::where('order_id', $order->id)->where('reason', 'cancel')->count())->toBe(2)
        ->and((float) ingredient('Café')->current_stock)->toBe(0.0);
});
