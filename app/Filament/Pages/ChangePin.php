<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Lets the owner change the dashboard PIN (stored hashed as the owner's password).
 *
 * @property-read Schema $form
 */
class ChangePin extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $slug = 'changer-pin';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Change PIN');
    }

    public function getTitle(): string
    {
        return __('Change PIN');
    }

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $pin = fn (string $name, string $label) => TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->required()
            ->extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*']);

        return $schema
            ->statePath('data')
            ->components([
                $pin('current_pin', __('Current PIN')),
                $pin('new_pin', __('New PIN'))
                    ->rule('digits_between:4,8')
                    ->validationMessages(['digits_between' => __('The PIN must have 4 to 8 digits.')]),
                $pin('new_pin_confirmation', __('Confirm the new PIN'))
                    ->same('new_pin')
                    ->validationMessages(['same' => __('The two PINs do not match.')]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('Save'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        if (! Hash::check((string) $data['current_pin'], $user->password)) {
            throw ValidationException::withMessages([
                'data.current_pin' => __('Current PIN is incorrect.'),
            ]);
        }

        $user->update(['password' => (string) $data['new_pin']]); // hashed by the model cast

        // Keep this session valid under AuthenticateSession (other sessions get logged out).
        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->form->fill();

        Notification::make()->title(__('PIN changed.'))->success()->send();
    }
}
