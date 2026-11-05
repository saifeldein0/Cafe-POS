<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    public const UNITS = ['g', 'ml', 'piece'];

    protected $guarded = [];

    /**
     * Translated, user-facing label for a unit code (g, ml, piece).
     */
    public static function labelForUnit(?string $unit): string
    {
        return $unit === 'piece' ? __('piece') : (string) $unit;
    }

    /**
     * @return array<string, string>
     */
    public static function unitOptions(): array
    {
        return collect(self::UNITS)
            ->mapWithKeys(fn (string $unit): array => [$unit => self::labelForUnit($unit)])
            ->all();
    }

    public function unitLabel(): string
    {
        return self::labelForUnit($this->unit);
    }

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'units_per_package' => 'decimal:3',
            'last_unit_cost' => 'decimal:4',
        ];
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
