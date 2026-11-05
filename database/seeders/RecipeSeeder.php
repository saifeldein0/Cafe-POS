<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Product name => [ingredient name => quantity]. A NULL quantity means
     * "unknown" (milk): the owner fills it in later. Products absent here
     * (Mojito, Ice chocolate, supplements, Rami) have no recipe yet.
     *
     * @var array<string, array<string, int|null>>
     */
    public const RECIPES = [
        'Express' => ['Café' => 9, 'Sucre' => 5],
        'Direct' => ['Café' => 9, 'Sucre' => 5, 'Lait' => null],
        'Cappucine' => ['Café' => 9, 'Sucre' => 5, 'Lait' => null],
        'Chocolat au Lait' => ['Lait' => null],
        'Eau 0.5' => ['Eau 0.5L' => 1],
        'Eau 1.5' => ['Eau 1.5L' => 1],
        'Eau Glass' => ['Eau verre' => 1],
        'Canette' => ['Canette' => 1],
        'Gazouz' => ['Gazouz' => 1],
        'Croissant' => ['Croissant' => 1],
        'Gâteau' => ['Gâteau' => 1],
    ];

    public function run(): void
    {
        $ingredients = Ingredient::pluck('id', 'name');

        foreach (self::RECIPES as $productName => $lines) {
            $product = Product::where('name', $productName)->firstOrFail();

            foreach ($lines as $ingredientName => $quantity) {
                RecipeItem::firstOrCreate(
                    ['product_id' => $product->id, 'ingredient_id' => $ingredients[$ingredientName]],
                    ['quantity' => $quantity],
                );
            }
        }
    }
}
