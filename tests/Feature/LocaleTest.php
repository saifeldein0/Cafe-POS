<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withoutVite();
    openShift();
});

it('defaults to French', function () {
    expect(config('app.locale'))->toBe('fr')
        ->and(config('app.fallback_locale'))->toBe('en');

    $this->get('/pos')
        ->assertSuccessful()
        ->assertSee('<html lang="fr">', escape: false)
        ->assertSee('Commande en cours')
        ->assertSee('Vider');
});

it('switches to English through the toggle and remembers it', function () {
    $this->from('/pos')
        ->get('/locale/en')
        ->assertRedirect('/pos')
        ->assertSessionHas('locale', 'en');

    $this->get('/pos')
        ->assertSuccessful()
        ->assertSee('<html lang="en">', escape: false)
        ->assertSee('Current order')
        ->assertSee('Clear')
        ->assertDontSee('Commande en cours');

    $this->get('/locale/fr')->assertRedirect();

    $this->get('/pos')->assertSee('<html lang="fr">', escape: false);
});

it('rejects unsupported locales', function () {
    $this->get('/locale/xx')->assertNotFound();

    $this->get('/pos')->assertSee('<html lang="fr">', escape: false);
});

it('applies the locale to the owner panel', function () {
    $owner = User::owner();

    $this->actingAs($owner)->get('/admin/rapport-mensuel')
        ->assertSuccessful()
        ->assertSee('Rapport mensuel')
        ->assertSee('English');

    $this->get('/locale/en');

    $this->actingAs($owner)->get('/admin/rapport-mensuel')
        ->assertSuccessful()
        ->assertSee('Monthly report')
        ->assertSee('Français', escape: false);
});

it('translates resource forms and tables to English', function () {
    $owner = User::owner();

    $this->actingAs($owner)->get('/admin/ingredients/create')
        ->assertSuccessful()
        ->assertSee('Stock initial')
        ->assertSee('Unités par conditionnement', escape: false)
        ->assertSee('pièce', escape: false);

    $this->actingAs($owner)->get('/admin/products')
        ->assertSee('Coût estimé', escape: false);

    $this->get('/locale/en');

    $this->actingAs($owner)->get('/admin/ingredients/create')
        ->assertSuccessful()
        ->assertSee('Initial stock')
        ->assertSee('Units per package')
        ->assertSee('Last unit cost')
        ->assertSee('piece')
        ->assertDontSee('Stock initial')
        ->assertDontSee('Conditionnement')
        ->assertDontSee('pièce', escape: false);

    $this->actingAs($owner)->get('/admin/products')
        ->assertSuccessful()
        ->assertSee('Estimated cost')
        ->assertSee('Margin')
        ->assertSee('Category')
        ->assertDontSee('Coût estimé', escape: false)
        ->assertDontSee('Marge');
});

it('shows French validation messages', function () {
    expect(__('validation.required', ['attribute' => 'pin']))->toContain('obligatoire');
});
