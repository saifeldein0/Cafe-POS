<?php

use App\Filament\Auth\PinLogin;
use App\Filament\Pages\ChangePin;
use App\Filament\Pages\MonthlyReport;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\RecipeItemsRelationManager;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
    RateLimiter::clear(PinLogin::throttleKey());
});

it('seeds the owner with the default PIN', function () {
    expect(Hash::check('1234', User::owner()->password))->toBeTrue();
});

it('redirects guests from the dashboard to the PIN login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows a single PIN field on the login page', function () {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('PIN')
        ->assertDontSee('data.email', escape: false);
});

it('logs the owner in with the correct PIN', function () {
    Livewire::test(PinLogin::class)
        ->fillForm(['pin' => '1234'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs(User::owner());
});

it('rejects a wrong PIN', function () {
    Livewire::test(PinLogin::class)
        ->fillForm(['pin' => '0000'])
        ->call('authenticate')
        ->assertHasFormErrors(['pin']);

    $this->assertGuest();
});

it('throttles repeated failed attempts, even with the correct PIN', function () {
    $component = Livewire::test(PinLogin::class);

    foreach (range(1, PinLogin::MAX_ATTEMPTS) as $attempt) {
        $component->fillForm(['pin' => '9999'])->call('authenticate');
    }

    $component->fillForm(['pin' => '1234'])
        ->call('authenticate')
        ->assertHasFormErrors(['pin'])
        ->assertSee('Trop de tentatives');

    $this->assertGuest();
});

it('serves every admin page to the owner', function (string $url) {
    $this->actingAs(User::owner())->get($url)->assertSuccessful();
})->with([
    'dashboard' => '/admin',
    'orders' => '/admin/orders',
    'products' => '/admin/products',
    'product create' => '/admin/products/create',
    'ingredients' => '/admin/ingredients',
    'ingredient create' => '/admin/ingredients/create',
    'purchases' => '/admin/purchases',
    'purchase create' => '/admin/purchases/create',
    'expenses' => '/admin/expenses',
    'expense create' => '/admin/expenses/create',
    'monthly report' => '/admin/rapport-mensuel',
    'previous month report' => '/admin/rapport-mensuel?month=2026-10',
    'change PIN' => '/admin/changer-pin',
]);

it('serves record pages to the owner', function () {
    $product = Product::where('name', 'Cappucine')->firstOrFail();
    $order = app(OrderService::class)->create([$product->id => 2]);
    $owner = User::owner();

    $this->actingAs($owner)->get("/admin/products/{$product->id}/edit")->assertSuccessful();
    Livewire::test(RecipeItemsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->assertSuccessful()
        ->assertSee('Lait')
        ->assertSee('inconnu');
    $this->actingAs($owner)->get('/admin/ingredients/1/edit')->assertSuccessful();
    $this->actingAs($owner)->get("/admin/orders/{$order->id}")->assertSuccessful()->assertSee('Cappucine');
    $this->actingAs($owner)->get('/admin/rapport-mensuel')->assertSuccessful()->assertSee('Cappucine')->assertSee('~');
});

it('has no order creation or edit pages', function () {
    expect(Order::query()->exists())->toBeFalse();

    $this->actingAs(User::owner())->get('/admin/orders/create')->assertNotFound();
});

it('denies the panel to a non-owner user', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->get('/admin')->assertForbidden();
});

it('lets the owner change the PIN', function () {
    $owner = User::owner();
    $this->actingAs($owner);

    Livewire::test(ChangePin::class)
        ->fillForm(['current_pin' => '1234', 'new_pin' => '5678', 'new_pin_confirmation' => '5678'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('5678', $owner->fresh()->password))->toBeTrue();
});

it('refuses a PIN change with a wrong current PIN or bad confirmation', function () {
    $this->actingAs(User::owner());

    Livewire::test(ChangePin::class)
        ->fillForm(['current_pin' => '0000', 'new_pin' => '5678', 'new_pin_confirmation' => '5678'])
        ->call('save')
        ->assertHasFormErrors(['current_pin']);

    Livewire::test(ChangePin::class)
        ->fillForm(['current_pin' => '1234', 'new_pin' => '5678', 'new_pin_confirmation' => '8765'])
        ->call('save')
        ->assertHasFormErrors(['new_pin_confirmation']);

    expect(Hash::check('1234', User::owner()->password))->toBeTrue();
});

it('renders the monthly report page for a chosen month', function () {
    $this->actingAs(User::owner());

    Livewire::test(MonthlyReport::class)
        ->assertSuccessful()
        ->set('month', '2026-10')
        ->assertSee('Octobre 2026');
});
