<?php

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Services\PurchaseService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->service = app(PurchaseService::class);
});

it('increases stock and records a purchase movement', function () {
    $cafe = Ingredient::where('name', 'Café')->firstOrFail();

    $purchase = $this->service->create([
        'ingredient_id' => $cafe->id,
        'packages' => 2,
        'total_cost' => 50,
        'purchased_at' => '2026-11-01',
    ]);

    // 2 packages × 1000 g
    expect((float) $purchase->quantity)->toBe(2000.0)
        ->and((float) $cafe->fresh()->current_stock)->toBe(2000.0);

    $movement = StockMovement::where('purchase_id', $purchase->id)->sole();
    expect($movement->reason)->toBe('purchase')
        ->and((float) $movement->quantity)->toBe(2000.0)
        ->and($movement->ingredient_id)->toBe($cafe->id);
});

it('updates the ingredient last unit cost', function () {
    $lait = Ingredient::where('name', 'Lait')->firstOrFail();

    $this->service->create([
        'ingredient_id' => $lait->id,
        'packages' => 1,
        'total_cost' => 9,
        'purchased_at' => '2026-11-01',
    ]);

    // 9 TND / 6000 ml
    expect((float) $lait->fresh()->last_unit_cost)->toBe(0.0015);
});

it('uses an explicit quantity when given', function () {
    $eau = Ingredient::where('name', 'Eau 1.5L')->firstOrFail();

    $purchase = $this->service->create([
        'ingredient_id' => $eau->id,
        'packages' => 1,
        'quantity' => 8,
        'total_cost' => 4,
        'purchased_at' => '2026-11-01',
    ]);

    expect((float) $purchase->quantity)->toBe(8.0)
        ->and((float) $eau->fresh()->current_stock)->toBe(8.0)
        ->and((float) $eau->fresh()->last_unit_cost)->toBe(0.5);
});

it('keeps the previous unit cost when quantity is zero', function () {
    $cafe = Ingredient::where('name', 'Café')->firstOrFail();

    $this->service->create([
        'ingredient_id' => $cafe->id,
        'packages' => 0,
        'total_cost' => 10,
        'purchased_at' => '2026-11-01',
    ]);

    expect((float) $cafe->fresh()->last_unit_cost)->toBe(0.03);
});

it('deleting a purchase reverses its stock', function () {
    $cafe = Ingredient::where('name', 'Café')->firstOrFail();

    $purchase = $this->service->create([
        'ingredient_id' => $cafe->id,
        'packages' => 1,
        'total_cost' => 25,
        'purchased_at' => '2026-11-01',
    ]);

    $this->service->delete($purchase);

    expect($purchase->fresh())->toBeNull()
        ->and((float) $cafe->fresh()->current_stock)->toBe(0.0)
        ->and(StockMovement::where('ingredient_id', $cafe->id)->sum('quantity'))->toEqual(0);
});
