<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cashier's shift ("équipe"): just a start and an end time. Orders taken while it is open belong to it.
 */
class Shift extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    #[Scope]
    protected function finished(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * The shift currently open, if any.
     */
    public static function current(): ?self
    {
        return static::query()->open()->latest('started_at')->latest('id')->first();
    }

    /**
     * The most recently finished shift, if any.
     */
    public static function lastFinished(): ?self
    {
        return static::query()->finished()->latest('ended_at')->latest('id')->first();
    }
}
