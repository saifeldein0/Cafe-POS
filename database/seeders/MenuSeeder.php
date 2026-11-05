<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Demo cafe menu (made-up prices in TND). No chicha, by design.
     *
     * @var array<string, array<string, string>>
     */
    public const MENU = [
        'Boissons chaudes' => [
            'Express' => '1.500',
            'Direct' => '2.200',
            'Cappucine' => '2.400',
            'Chocolat au Lait' => '2.900',
        ],
        'Boissons froides' => [
            'Mojito' => '6.000',
            'Ice chocolate' => '3.800',
        ],
        'Bouteilles' => [
            'Eau 0.5' => '0.800',
            'Eau 1.5' => '1.300',
            'Eau Glass' => '1.600',
            'Canette' => '2.200',
            'Gazouz' => '1.400',
        ],
        'Suppléments' => [
            'Nestlé' => '0.300',
            'Mente' => '0.400',
            'Chocoline' => '0.700',
        ],
        'Desserts' => [
            'Croissant' => '1.200',
            'Gâteau' => '2.800',
        ],
        'Jeux' => [
            'Rami' => '3.000',
            '1/4 Rami' => '0.800',
        ],
    ];

    public function run(): void
    {
        $categoryOrder = 0;

        foreach (self::MENU as $categoryName => $products) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['sort_order' => ++$categoryOrder * 10],
            );

            $productOrder = 0;
            foreach ($products as $name => $price) {
                Product::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    ['price' => $price, 'is_active' => true, 'sort_order' => ++$productOrder * 10],
                );
            }
        }
    }
}
