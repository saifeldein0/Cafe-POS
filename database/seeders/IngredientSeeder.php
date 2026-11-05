<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        // Demo values (made-up prices in TND): [name, unit, package_name, units_per_package, package_price]
        $ingredients = [
            ['Café', 'g', 'Paquet 1 kg', 1000, 30.000],
            ['Sucre', 'g', 'Sachet 5 g', 5, 0.050],
            ['Lait', 'ml', 'Pack 6 × 1 L', 6000, 7.200],
            ['Eau 1.5L', 'piece', 'Carton', 6, 3.300], // carton size unknown, editable
            ['Eau 0.5L', 'piece', 'Carton 12', 12, 4.200],
            ['Eau verre', 'piece', 'Carton 12', 12, 8.400],
            ['Gazouz', 'piece', 'Carton 24', 24, 16.800],
            ['Canette', 'piece', 'Carton 24', 24, 31.200],
            ['Croissant', 'piece', 'Pièce', 1, 0.700],
            ['Gâteau', 'piece', 'Pièce', 1, 1.600],
            ['Jeu de cartes Rami', 'piece', 'Jeu', 1, 5.000],
        ];

        foreach ($ingredients as [$name, $unit, $packageName, $unitsPerPackage, $packagePrice]) {
            Ingredient::firstOrCreate(
                ['name' => $name],
                [
                    'unit' => $unit,
                    'current_stock' => 0,
                    'package_name' => $packageName,
                    'units_per_package' => $unitsPerPackage,
                    'last_unit_cost' => round($packagePrice / $unitsPerPackage, 4),
                ],
            );
        }
    }
}
