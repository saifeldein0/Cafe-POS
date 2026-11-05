<?php

use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('computes the recipe cost of an Express', function () {
    // 9 g × 0.030 + 5 g × 0.010 = 0.270 + 0.050
    expect(Product::where('name', 'Express')->first()->recipeCost())->toEqualWithDelta(0.32, 0.0001);
});

it('returns null recipe cost for products without a recipe', function () {
    expect(Product::where('name', 'Mojito')->first()->recipeCost())->toBeNull()
        ->and(Product::where('name', 'Rami')->first()->recipeCost())->toBeNull();
});

it('ignores unknown quantities in recipe cost', function () {
    // Cappucine has café + sucre known, lait unknown
    expect(Product::where('name', 'Cappucine')->first()->recipeCost())->toEqualWithDelta(0.32, 0.0001)
        ->and(Product::where('name', 'Chocolat au Lait')->first()->recipeCost())->toEqual(0.0);
});

it('computes recipe cost for a bottle', function () {
    // Eau 0.5L: 4.2 TND / 12
    expect(Product::where('name', 'Eau 0.5')->first()->recipeCost())->toEqualWithDelta(4.2 / 12, 0.0001);
});

it('flags incomplete recipes', function (string $name, bool $incomplete) {
    expect(Product::where('name', $name)->first()->hasIncompleteRecipe())->toBe($incomplete);
})->with([
    ['Express', false],
    ['Croissant', false],
    ['Cappucine', true],
    ['Direct', true],
    ['Chocolat au Lait', true],
    ['Mojito', true],
    ['Nestlé', true],
    ['Rami', true],
]);

it('recipe cost follows ingredient unit cost changes', function () {
    Ingredient::where('name', 'Café')->update(['last_unit_cost' => 0.05]);

    expect(Product::where('name', 'Express')->first()->recipeCost())->toEqualWithDelta(0.45 + 0.05, 0.0001);
});

it('prorates expenses monthly', function () {
    expect(Expense::where('name', 'beIN Sports')->first()->monthlyAmount())->toEqualWithDelta(56.667, 0.001)
        ->and(Expense::where('name', 'Loyer')->first()->monthlyAmount())->toEqual(1200.0);
});

it('seeds the menu without any chicha', function () {
    expect(Product::count())->toBe(18)
        ->and(Product::where('name', 'like', '%chicha%')->count())->toBe(0);
});

it('seeding twice is idempotent', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(18)
        ->and(Ingredient::count())->toBe(11)
        ->and(Expense::count())->toBe(5);
});
