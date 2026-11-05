<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * PIN-only login: a single numeric field checked against the owner's hashed password.
 * On success the owner user is signed in on the panel's session guard.
 */
class PinLogin extends Login
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        $key = static::throttleKey();

        if (RateLimiter::tooManyAttempts($key, static::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'data.pin' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
            ]);
        }

        $data = $this->form->getState();
        $owner = User::owner();

        if (! $owner || ! Hash::check((string) $data['pin'], $owner->password)) {
            RateLimiter::hit($key, static::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'data.pin' => 'PIN incorrect.',
            ]);
        }

        RateLimiter::clear($key);

        Filament::auth()->login($owner);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public static function throttleKey(): string
    {
        return 'pin-login|'.request()->ip();
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPinFormComponent(),
        ]);
    }

    protected function getPinFormComponent(): Component
    {
        return TextInput::make('pin')
            ->label('PIN')
            ->password()
            ->revealable()
            ->required()
            ->autofocus()
            ->autocomplete('current-password')
            ->extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*']);
    }

    #[Unexposed]
    public function getTitle(): string|Htmlable
    {
        return 'Connexion';
    }

    #[Unexposed]
    public function getHeading(): string|Htmlable|null
    {
        return 'Espace gérant';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Se connecter');
    }

    #[Unexposed]
    public function getSubheading(): string|Htmlable|null
    {
        return 'Entrez votre PIN';
    }
}
