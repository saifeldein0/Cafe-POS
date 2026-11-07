<?php

use App\Exceptions\NoOpenShiftException;
use App\Exceptions\ShiftAlreadyOpenException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Services\OrderService;
use App\Services\ShiftService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->shifts = app(ShiftService::class);
    $this->orders = app(OrderService::class);
    $this->id = fn (string $name): int => Product::where('name', $name)->value('id');
});

it('starts and ends a shift', function () {
    $this->travelTo('2026-11-07 08:02:00');
    $shift = $this->shifts->start();

    expect($shift->isOpen())->toBeTrue()
        ->and($shift->started_at->format('Y-m-d H:i'))->toBe('2026-11-07 08:02')
        ->and(Shift::current()->is($shift))->toBeTrue()
        ->and(Shift::lastFinished())->toBeNull();

    $this->travelTo('2026-11-07 15:30:00');
    $this->shifts->end($shift);

    expect($shift->isOpen())->toBeFalse()
        ->and($shift->fresh()->ended_at->format('Y-m-d H:i'))->toBe('2026-11-07 15:30')
        ->and(Shift::current())->toBeNull()
        ->and(Shift::lastFinished()->is($shift))->toBeTrue();

    // Ending twice is harmless: the end time stays.
    $this->travelTo('2026-11-07 16:00:00');
    $this->shifts->end($shift->fresh());

    expect($shift->fresh()->ended_at->format('H:i'))->toBe('15:30');
});

it('refuses to start a second shift while one is open', function () {
    $this->shifts->start();

    expect(fn () => $this->shifts->start())->toThrow(ShiftAlreadyOpenException::class)
        ->and(Shift::count())->toBe(1);
});

it('refuses an order when no shift is open and records nothing', function () {
    $cafe = Ingredient::where('name', 'Café')->firstOrFail();

    expect(fn () => $this->orders->create([($this->id)('Express') => 2]))->toThrow(NoOpenShiftException::class)
        ->and(Order::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and((float) $cafe->fresh()->current_stock)->toBe(0.0);

    $shift = $this->shifts->start();
    $this->shifts->end($shift);

    expect(fn () => $this->orders->create([($this->id)('Express') => 1]))->toThrow(NoOpenShiftException::class)
        ->and(Order::count())->toBe(0);
});

it('attaches orders to the open shift', function () {
    $first = $this->shifts->start();
    $a = $this->orders->create([($this->id)('Express') => 1]);
    $this->shifts->end($first);

    $second = $this->shifts->start();
    $b = $this->orders->create([($this->id)('Croissant') => 1]);

    expect($a->shift_id)->toBe($first->id)
        ->and($b->shift_id)->toBe($second->id)
        ->and($b->shift->is($second))->toBeTrue()
        ->and($first->orders()->count())->toBe(1);
});

it('summarises a shift: lines by product, valid total, cancelled orders apart', function () {
    $id = $this->id;

    // Another shift's sales never leak into this receipt.
    $other = $this->shifts->start();
    $this->orders->create([$id('Gâteau') => 4]);
    $this->shifts->end($other);

    $shift = $this->shifts->start();
    $this->orders->create([$id('Express') => 2, $id('Croissant') => 1]); // 3.000 + 1.200
    $this->orders->create([$id('Express') => 1]);                        // 1.500
    $cancelled = $this->orders->create([$id('Mojito') => 1]);           // 6.000, cancelled
    $this->orders->cancel($cancelled);
    $this->shifts->end($shift);

    $summary = $this->shifts->summary($shift->fresh());

    expect($summary->lines->all())->toBe([
        ['name' => 'Croissant', 'quantity' => 1, 'amount' => 1.2],
        ['name' => 'Express', 'quantity' => 3, 'amount' => 4.5],
    ])
        ->and($summary->ordersCount)->toBe(2)
        ->and($summary->total)->toBe(5.7)
        ->and($summary->cancelledCount)->toBe(1)
        ->and($summary->cancelledTotal)->toBe(6.0)
        ->and($summary->endedAt)->not->toBeNull();
});

it('reflects a cancellation made after the shift ended', function () {
    $shift = $this->shifts->start();
    $this->orders->create([($this->id)('Express') => 1]);
    $late = $this->orders->create([($this->id)('Croissant') => 2]);
    $this->shifts->end($shift);

    expect($this->shifts->summary($shift)->total)->toBe(3.9);

    $this->orders->cancel($late);

    $summary = $this->shifts->summary($shift);

    expect($summary->total)->toBe(1.5)
        ->and($summary->ordersCount)->toBe(1)
        ->and($summary->cancelledCount)->toBe(1)
        ->and($summary->lines->pluck('name')->all())->toBe(['Express']);
});

it('summarises an empty shift', function () {
    $summary = $this->shifts->summary($this->shifts->start());

    expect($summary->lines)->toBeEmpty()
        ->and($summary->ordersCount)->toBe(0)
        ->and($summary->total)->toBe(0.0)
        ->and($summary->cancelledCount)->toBe(0)
        ->and($summary->endedAt)->toBeNull();
});

it('leaves orders recorded before shifts untouched', function () {
    $this->travelTo('2026-10-15 10:00:00');
    $legacy = Order::create(['total' => 3.0]);
    $legacy->items()->create([
        'product_id' => ($this->id)('Express'),
        'product_name' => 'Express',
        'unit_price' => 1.5,
        'quantity' => 2,
        'line_total' => 3.0,
    ]);
    $before = $legacy->fresh()->getAttributes();

    $this->travelTo('2026-11-07 08:00:00');
    $shift = $this->shifts->start();
    $this->orders->create([($this->id)('Express') => 1]);
    $this->shifts->end($shift);
    $this->shifts->start();

    expect($legacy->fresh()->getAttributes())->toBe($before)
        ->and($legacy->fresh()->shift_id)->toBeNull()
        ->and($this->shifts->summary($shift)->ordersCount)->toBe(1)
        ->and($this->shifts->summary($shift)->lines->sole()['quantity'])->toBe(1);
});
