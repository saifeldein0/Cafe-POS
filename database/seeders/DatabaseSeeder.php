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
            ['email' => User::OWNER_EMAIL],
            // The owner's password is the dashboard PIN (hashed by the model cast).
            ['name' => 'Owner', 'password' => (string) env('OWNER_PIN', '1234')],
        );

        $this->call([
            MenuSeeder::class,
            IngredientSeeder::class,
            RecipeSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
