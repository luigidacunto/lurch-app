<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class AdminLogin extends Login
{
    /**
     * Il login via email/OTP è il default: /admin/login redirige a /admin-login
     * a meno che non sia esplicitamente richiesto il form password (link di fallback,
     * o password temporanea impostata da un superadmin).
     */
    public function mount(): void
    {
        if (! request()->boolean('password')) {
            $this->redirect(route('admin-login.form'));

            return;
        }

        parent::mount();
    }

    public function authenticate(): ?LoginResponse
    {
        $email = $this->data['email'] ?? null;

        // Account senza password: evita di passare null a Hash::check(), reindirizza al login via email.
        if ($email && User::where('email', $email)->whereNull('password')->exists()) {
            throw ValidationException::withMessages([
                'data.email' => 'Questo account non ha una password: usa "Accedi senza password" qui sotto.',
            ]);
        }

        return parent::authenticate();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->hint(new HtmlString(Blade::render(
                '<x-filament::link :href="$url" tabindex="-1">Accedi senza password</x-filament::link>',
                ['url' => route('admin-login.form')]
            )))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
    }

    // Niente "ricordami": la sessione deve sempre avere una scadenza (vedi config/session.php).
    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return new HtmlString('Problemi anche con la password? Contatta il gestore del portale: può impostartene una temporanea.');
    }
}
