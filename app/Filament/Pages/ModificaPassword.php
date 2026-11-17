<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio della password temporanea. Si accede via email (link o codice); la password esiste solo
 * come soluzione d'emergenza impostata da un superadmin e si disattiva al primo accesso via email
 * (User::completaLoginEmail()): la pagina serve quindi solo a chi una password ce l'ha.
 */
class ModificaPassword extends Page
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.modifica-password';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Modifica password';

    protected static ?string $slug = 'modifica-password';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->password !== null;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('password_attuale')
                    ->label('Password attuale')
                    ->password()
                    ->required()
                    ->revealable(),

                TextInput::make('password')
                    ->label('Nuova password')
                    ->password()
                    ->required()
                    ->revealable()
                    ->rule(Password::min(8))
                    ->different('password_attuale')
                    ->helperText('La password è una soluzione temporanea: si disattiva da sola al prossimo accesso via email.'),

                TextInput::make('password_confirmation')
                    ->label('Conferma nuova password')
                    ->password()
                    ->required()
                    ->revealable()
                    ->same('password'),
            ])
            ->statePath('data');
    }

    public function salva(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        if (! Hash::check($data['password_attuale'], $user->password)) {
            $this->addError('data.password_attuale', 'La password attuale non è corretta.');
            return;
        }

        $user->update(['password' => Hash::make($data['password'])]);

        $this->form->fill();

        Notification::make()
            ->title('Password aggiornata con successo.')
            ->success()
            ->send();
    }
}
