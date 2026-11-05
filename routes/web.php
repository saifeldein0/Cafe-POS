<?php

use App\Http\Middleware\SetLocale;
use App\Livewire\Pos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/pos');

Route::livewire('/pos', Pos::class)->name('pos');

Route::get('/locale/{locale}', function (string $locale): RedirectResponse {
    session([SetLocale::SESSION_KEY => $locale]);

    return redirect()->back(fallback: route('pos'));
})->whereIn('locale', SetLocale::SUPPORTED)->name('locale');
