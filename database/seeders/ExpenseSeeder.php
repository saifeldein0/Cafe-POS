<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo values (made-up amounts in TND): [name, amount, period_months]
        $expenses = [
            ['Loyer', 1200, 1],
            ['Électricité', 350, 1],
            ['Internet', 60, 1],
            ['beIN Sports', 170, 3],
            ['Salaires', 1800, 1],
        ];

        foreach ($expenses as [$name, $amount, $periodMonths]) {
            Expense::firstOrCreate(
                ['name' => $name],
                ['amount' => $amount, 'period_months' => $periodMonths, 'is_active' => true],
            );
        }
    }
}
