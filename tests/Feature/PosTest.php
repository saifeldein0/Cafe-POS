<?php

use App\Livewire\Pos;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use App\Services\OrderService;
use App\Services\ShiftService;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    openShift();
});

function posProduct(string $name): Product
{
    return Product::where('name', $name)->firstOrFail();
}

it('renders the seeded categories and the first category products', function () {
    $first = Category::orderBy('sort_order')->firstOrFail();

    $component = Livewire::test(Pos::class)
        ->assertSuccessful()
        ->assertSet('categoryId', $first->id);

    foreach (Category::all() as $category) {
        $component->assertSee($category->name);
    }

    $component->assertSee('Express')
        ->assertSee('1.500 DT')
        ->assertDontSee('Mojito');
});

it('switches category', function () {
    $cold = Category::where('name', 'Boissons froides')->firstOrFail();

    Livewire::test(Pos::class)
        ->call('selectCategory', $cold->id)
        ->assertSet('categoryId', $cold->id)
        ->assertSee('Mojito')
        ->assertDontSee('Express');
});

it('does not show inactive products', function () {
    posProduct('Direct')->update(['is_active' => false]);

    Livewire::test(Pos::class)
        ->assertSee('Express')
        ->assertDontSee('Direct');
});

it('ignores taps on inactive or unknown products', function () {
    $direct = posProduct('Direct');
    $direct->update(['is_active' => false]);

    Livewire::test(Pos::class)
        ->call('addItem', $direct->id)
        ->call('addItem', 999999)
        ->assertSet('cart', []);
});

it('adds, increments, decrements and removes items while keeping the total', function () {
    $express = posProduct('Express');   // 1.500
    $croissant = posProduct('Croissant'); // 1.200

    $component = Livewire::test(Pos::class)
        ->call('addItem', $express->id)
        ->call('addItem', $express->id)
        ->call('addItem', $croissant->id)
        ->assertSet('cart', [$express->id => 2, $croissant->id => 1]);

    expect($component->get('total'))->toBe(4.2);
    $component->assertSee('4.200 DT');

    $component->call('increment', $croissant->id)
        ->assertSet("cart.{$croissant->id}", 2);
    expect($component->get('total'))->toBe(5.4);

    $component->call('decrement', $express->id)
        ->call('decrement', $express->id)
        ->assertSet('cart', [$croissant->id => 2]);
    expect($component->get('total'))->toBe(2.4);

    $component->call('removeItem', $croissant->id)
        ->assertSet('cart', []);
    expect($component->get('total'))->toBe(0.0);
});

it('clears the order', function () {
    Livewire::test(Pos::class)
        ->call('addItem', posProduct('Express')->id)
        ->call('addItem', posProduct('Gâteau')->id)
        ->call('clear')
        ->assertSet('cart', []);

    expect(Order::count())->toBe(0);
});

it('confirms an order through the order service and resets the cart', function () {
    $express = posProduct('Express');   // 1.500
    $canette = posProduct('Canette');   // 2.200

    Livewire::test(Pos::class)
        ->call('addItem', $express->id)
        ->call('addItem', $express->id)
        ->call('addItem', $canette->id)
        ->call('confirm')
        ->assertSet('cart', [])
        ->assertSet('notice.type', 'success')
        ->assertSee('Commande enregistrée ✓')
        ->assertSee('5.200 DT');

    $order = Order::with('items')->sole();

    expect((float) $order->total)->toBe(5.2)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('product_id', $express->id)->quantity)->toBe(2)
        ->and((float) $order->items->firstWhere('product_id', $express->id)->line_total)->toBe(3.0)
        ->and((float) $order->items->firstWhere('product_id', $canette->id)->line_total)->toBe(2.2);
});

it('does nothing but warn when confirming an empty order', function () {
    Livewire::test(Pos::class)
        ->call('confirm')
        ->assertSet('notice.type', 'error')
        ->assertSee('La commande est vide');

    expect(Order::count())->toBe(0);
});

it('drops products deactivated meanwhile instead of failing', function () {
    $express = posProduct('Express');
    $croissant = posProduct('Croissant');

    $component = Livewire::test(Pos::class)
        ->call('addItem', $express->id)
        ->call('addItem', $croissant->id);

    $croissant->update(['is_active' => false]);

    $component->call('confirm')
        ->assertSet('notice.type', 'error')
        ->assertSet('cart', [$express->id => 1]);

    expect(Order::count())->toBe(0);
});

it('serves the POS page to guests and redirects the home page to it', function () {
    $this->get('/')->assertRedirect('/pos');

    $this->get('/pos')
        ->assertSuccessful()
        ->assertSeeLivewire(Pos::class)
        ->assertSee('Confirmer');
});

/*
 * Shifts. beforeEach opened one; endOpenShift() puts the till in its locked state.
 */
function endOpenShift(): Shift
{
    $shift = Shift::current();
    app(ShiftService::class)->end($shift);

    return $shift->fresh();
}

it('shows the open shift in the header', function () {
    Shift::current()->update(['started_at' => now()->setTime(8, 2)]);

    Livewire::test(Pos::class)
        ->assertSee('Équipe depuis 08:02')
        ->assertSee("Terminer l'équipe")
        ->assertDontSee('Reçu précédent'); // no finished shift yet
});

it('locks the till when no shift is open', function () {
    endOpenShift();

    Livewire::test(Pos::class)
        ->assertSee('Caisse fermée')
        ->assertSee('Nouvelle équipe')
        ->assertDontSee('Express')
        ->assertDontSee('Commande en cours')
        ->call('addItem', posProduct('Express')->id)
        ->assertSet('cart', []);
});

it('starts a new shift only after the confirmation step', function () {
    endOpenShift();

    $component = Livewire::test(Pos::class)
        ->call('startShift'); // no confirmation asked: nothing happens

    expect(Shift::current())->toBeNull();

    $component->call('askNewShift')
        ->assertSet('confirming', Pos::CONFIRM_START)
        ->assertSee('Commencer une nouvelle équipe ?')
        ->call('cancelConfirm')
        ->assertSet('confirming', null);

    expect(Shift::current())->toBeNull();

    $component->call('askNewShift')
        ->call('startShift')
        ->assertSet('confirming', null)
        ->assertSee('Express')
        ->assertSee('Équipe depuis')
        ->assertSee('Nouvelle équipe commencée ✓');

    expect(Shift::current())->not->toBeNull()
        ->and(Shift::count())->toBe(2);
});

it('refuses to end the shift while an order is in progress', function () {
    Livewire::test(Pos::class)
        ->call('addItem', posProduct('Express')->id)
        ->call('askEndShift')
        ->assertSet('confirming', null)
        ->assertSet('notice.type', 'error')
        ->assertSee("Confirmez ou videz la commande en cours d'abord.")
        ->assertSet('cart', [posProduct('Express')->id => 1]);

    expect(Shift::current())->not->toBeNull();
});

it('ends the shift after the confirmation step and shows its receipt', function () {
    $express = posProduct('Express');   // 1.500
    $canette = posProduct('Canette');   // 2.200
    $croissant = posProduct('Croissant'); // 1.200

    $component = Livewire::test(Pos::class)
        ->call('addItem', $express->id)
        ->call('addItem', $express->id)
        ->call('addItem', $canette->id)
        ->call('confirm')
        ->call('addItem', $croissant->id)
        ->call('confirm');

    // A cancelled order is shown apart and left out of the total.
    $cancelled = app(OrderService::class)->create([$canette->id => 2]);
    app(OrderService::class)->cancel($cancelled);

    $component->call('endShift'); // no confirmation asked: nothing happens
    expect(Shift::current())->not->toBeNull();

    $component->call('askEndShift')
        ->assertSet('confirming', Pos::CONFIRM_END)
        ->assertSee("Terminer l'équipe ?")
        ->assertSee("La caisse sera bloquée jusqu'à la nouvelle équipe.")
        ->call('cancelConfirm');
    expect(Shift::current())->not->toBeNull();

    $component->call('askEndShift')
        ->call('endShift')
        ->assertSet('confirming', null)
        ->assertSee('Caisse fermée')
        ->assertSee("Reçu de l'équipe")
        ->assertSeeInOrder(['Canette', '2.200 DT', 'Croissant', '1.200 DT', 'Express', '3.000 DT'])
        ->assertSee('Commandes annulées (non comptées) : 1')
        ->assertSee('4.400 DT')
        ->assertSee('6.400 DT')
        ->assertDontSee('Commande en cours');

    $receipt = $component->get('receipt');

    expect(Shift::current())->toBeNull()
        ->and($receipt->lines->pluck('quantity', 'name')->all())->toBe(['Canette' => 1, 'Croissant' => 1, 'Express' => 2])
        ->and($receipt->ordersCount)->toBe(2)
        ->and($receipt->total)->toBe(6.4)
        ->and($receipt->cancelledCount)->toBe(1)
        ->and($receipt->cancelledTotal)->toBe(4.4);
});

it('opens the previous receipt during the next shift without changing anything', function () {
    Livewire::test(Pos::class)
        ->call('addItem', posProduct('Express')->id)
        ->call('confirm');
    $finished = endOpenShift();
    $current = app(ShiftService::class)->start();

    $orders = Order::count();
    $shifts = Shift::query()->orderBy('id')->get()->map->getAttributes()->all();

    Livewire::test(Pos::class)
        ->assertSee('Reçu précédent')
        ->assertDontSee("Reçu de l'équipe")
        ->call('openReceipt')
        ->assertSet('showReceipt', true)
        ->assertSee("Reçu de l'équipe")
        ->assertSee($finished->started_at->format('d/m/Y H:i'))
        ->assertSee('1.500 DT')
        ->assertSee('Commande en cours') // the till is still behind the overlay
        ->call('closeReceipt')
        ->assertSet('showReceipt', false)
        ->assertDontSee("Reçu de l'équipe")
        ->call('openReceipt')
        ->assertSet('showReceipt', true);

    expect(Order::count())->toBe($orders)
        ->and(Shift::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($shifts)
        ->and(Shift::current()->is($current))->toBeTrue();
});

it('brings the receipt back on the locked screen after it was closed', function () {
    Livewire::test(Pos::class)
        ->call('addItem', posProduct('Croissant')->id)
        ->call('confirm');
    endOpenShift();

    Livewire::test(Pos::class)
        ->assertSee("Reçu de l'équipe")
        ->assertDontSee('Reçu précédent') // the receipt is already on screen
        ->call('dismissReceipt')
        ->assertSet('receiptDismissed', true)
        ->assertDontSee("Reçu de l'équipe")
        ->assertSee('Reçu précédent')
        ->assertSee('Nouvelle équipe')
        ->call('openReceipt')
        ->assertSet('receiptDismissed', false)
        ->assertSee("Reçu de l'équipe")
        ->assertSee('1.200 DT');

    expect(Shift::current())->toBeNull()
        ->and(Order::count())->toBe(1);
});

it('keeps the order when the shift was ended from another screen', function () {
    $express = posProduct('Express');

    $component = Livewire::test(Pos::class)
        ->call('addItem', $express->id);

    endOpenShift();

    $component->call('confirm')
        ->assertSet('notice.type', 'error')
        ->assertSet('cart', [$express->id => 1])
        ->assertSee('Aucune équipe en cours');

    expect(Order::count())->toBe(0);
});
