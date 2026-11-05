<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Estimated ingredient cost of one unit, using each ingredient's last unit cost.
     * Lines with an unknown (NULL) quantity are skipped; null when there is no recipe at all.
     */
    public function recipeCost(): ?float
    {
        $items = $this->recipeItems()->with('ingredient')->get();

        if ($items->isEmpty()) {
            return null;
        }

        return (float) $items
            ->whereNotNull('quantity')
            ->sum(fn (RecipeItem $item) => (float) $item->quantity * (float) $item->ingredient->last_unit_cost);
    }

    /**
     * True when the recipe is empty or any line has an unknown quantity.
     */
    public function hasIncompleteRecipe(): bool
    {
        $items = $this->recipeItems()->get(['id', 'quantity']);

        return $items->isEmpty() || $items->contains(fn (RecipeItem $item) => $item->quantity === null);
    }
}
