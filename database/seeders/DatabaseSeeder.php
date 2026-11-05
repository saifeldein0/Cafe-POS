<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Safe to run repeatedly.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'owner@cafepos.local'],
            ['name' => 'Owner', 'password' => env('OWNER_PASSWORD', 'cafepos')], // hashed by the model cast
        );

        $this->call([
            MenuSeeder::class,
            IngredientSeeder::class,
            RecipeSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
