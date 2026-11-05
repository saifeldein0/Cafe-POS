<?php

namespace App\Providers\Filament;

use App\Filament\Auth\PinLogin;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\ChangePin;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Hand-tuned ramps, so the 600 shade is the till's own copper (#8f4a1d area) and holds white text.
     * (Color::hex() would normalise the hue into a bright orange.)
     */
    private const ROASTED_COPPER = [
        50 => 'oklch(0.975 0.012 60)',
        100 => 'oklch(0.945 0.028 58)',
        200 => 'oklch(0.89 0.055 56)',
        300 => 'oklch(0.8 0.09 54)',
        400 => 'oklch(0.7 0.12 52)',
        500 => 'oklch(0.6 0.13 50)',
        600 => 'oklch(0.52 0.125 48)',
        700 => 'oklch(0.46 0.11 47)',
        800 => 'oklch(0.39 0.09 46)',
        900 => 'oklch(0.33 0.07 45)',
        950 => 'oklch(0.24 0.05 44)',
    ];

    private const MINT_TEA = [
        50 => 'oklch(0.975 0.015 160)',
        100 => 'oklch(0.945 0.035 160)',
        200 => 'oklch(0.89 0.065 160)',
        300 => 'oklch(0.8 0.1 160)',
        400 => 'oklch(0.7 0.12 160)',
        500 => 'oklch(0.6 0.12 160)',
        600 => 'oklch(0.5 0.105 160)',
        700 => 'oklch(0.44 0.09 160)',
        800 => 'oklch(0.37 0.075 160)',
        900 => 'oklch(0.31 0.06 160)',
        950 => 'oklch(0.22 0.04 160)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('CafePOS')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            // The cafe's palette, shared with the cash register: roasted copper, warm stone greys,
            // mint-tea green. System font and local avatars: the panel makes no outside requests.
            ->colors([
                'primary' => self::ROASTED_COPPER,
                'gray' => Color::Stone,
                'success' => self::MINT_TEA,
            ])
            ->font('Segoe UI Variable Text', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->login(PinLogin::class)
            ->userMenuItems([
                'change-pin' => Action::make('change-pin')
                    ->label(fn (): string => __('Change PIN'))
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn (): string => ChangePin::getUrl()),
                'locale' => Action::make('locale')
                    ->label(fn (): string => app()->getLocale() === 'fr' ? 'English' : 'Français')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn (): string => route('locale', app()->getLocale() === 'fr' ? 'en' : 'fr')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
