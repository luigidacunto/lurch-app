<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ModificaPassword extends Page
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.modifica-password';

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Modifica password';

    protected static ?string $slug = 'modifica-password';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Placeholder::make('info_no_password')
                    ->label('')
                    ->content('Il tuo account non ha una password: accedi tramite email. Puoi impostarne una qui sotto per tornare al login classico.')
                    ->visible(fn () => auth()->user()?->password === null),

                TextInput::make('password_attuale')
                    ->label('Password attuale')
                    ->password()
                    ->required()
                    ->revealable()
                    ->visible(fn () => auth()->user()?->password !== null),

                TextInput::make('password')
                    ->label('Nuova password')
                    ->password()
                    ->required()
                    ->revealable()
                    ->rule(Password::min(8))
                    ->different('password_attuale'),

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

        if ($user->password !== null && ! Hash::check($data['password_attuale'], $user->password)) {
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
