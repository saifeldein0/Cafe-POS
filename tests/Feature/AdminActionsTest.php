<?php

use App\Filament\Resources\Ingredients\Pages\ListIngredients;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\RecipeItemsRelationManager;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseService;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::owner());
    $this->cafe = Ingredient::where('name', 'Café')->firstOrFail();
});

it('adjusts stock through the ledger', function () {
    $movement = app(StockService::class)->adjust($this->cafe, -12.5, 'Paquet renversé');

    expect($movement->reason)->toBe(StockMovement::REASON_ADJUSTMENT)
        ->and((float) $movement->quantity)->toBe(-12.5)
        ->and($movement->note)->toBe('Paquet renversé')
        ->and((float) $this->cafe->fresh()->current_stock)->toBe(-12.5);
});

it('adjusts stock from the ingredients table action', function () {
    Livewire::test(ListIngredients::class)
        ->callAction(TestAction::make('adjustStock')->table($this->cafe), ['quantity' => 500, 'note' => 'Inventaire'])
        ->assertHasNoFormErrors();

    expect((float) $this->cafe->fresh()->current_stock)->toBe(500.0)
        ->and(StockMovement::where('reason', StockMovement::REASON_ADJUSTMENT)->count())->toBe(1);
});

it('creates a purchase through PurchaseService with auto-computed quantity', function () {
    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'ingredient_id' => $this->cafe->id,
            'packages' => 2,
            'total_cost' => 52,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertSchemaStateSet(['quantity' => 2000.0])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::sole();
    $cafe = $this->cafe->fresh();

    expect((float) $purchase->quantity)->toBe(2000.0)
        ->and((float) $cafe->current_stock)->toBe(2000.0)
        ->and((float) $cafe->last_unit_cost)->toBe(0.026)
        ->and($purchase->stockMovements()->count())->toBe(1);
});

it('deletes a purchase through PurchaseService, reversing stock', function () {
    $purchase = app(PurchaseService::class)->create([
        'ingredient_id' => $this->cafe->id, 'packages' => 1, 'total_cost' => 25, 'purchased_at' => now()->toDateString(),
    ]);

    Livewire::test(ListPurchases::class)
        ->callAction(TestAction::make('delete')->table($purchase));

    expect(Purchase::count())->toBe(0)
        ->and((float) $this->cafe->fresh()->current_stock)->toBe(0.0)
        ->and(StockMovement::count())->toBe(0);
});

it('lets the owner fill in an unknown recipe quantity', function () {
    $cappucine = Product::where('name', 'Cappucine')->firstOrFail();
    $milkLine = $cappucine->recipeItems()->whereNull('quantity')->sole();

    expect($cappucine->hasIncompleteRecipe())->toBeTrue();

    Livewire::test(RecipeItemsRelationManager::class, ['ownerRecord' => $cappucine, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('edit')->table($milkLine), ['quantity' => 120])
        ->assertHasNoFormErrors();

    expect((float) $milkLine->fresh()->quantity)->toBe(120.0)
        ->and($cappucine->hasIncompleteRecipe())->toBeFalse();
});

it('cancels an order from the view page and hides the action afterwards', function () {
    $express = Product::where('name', 'Express')->firstOrFail();
    $order = app(OrderService::class)->create([$express->id => 1]);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('cancel');

    expect($order->fresh()->isCancelled())->toBeTrue()
        ->and((float) $this->cafe->fresh()->current_stock)->toBe(0.0);

    Livewire::test(ListOrders::class)
        ->assertActionHidden(TestAction::make('cancel')->table($order->fresh()));
});
