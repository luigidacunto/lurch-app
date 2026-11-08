<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImpostaPasswordTemporanea extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:imposta-password-temporanea {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imposta una password temporanea per un utente, riattiva il login con password e invalida le sue sessioni attive (fallback via SSH quando il pannello non è raggiungibile).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Nessun utente trovato con questa email.');
            return self::FAILURE;
        }

        $password = Str::password(16);
        $user->update(['password' => Hash::make($password)]);
        $user->invalidaSessioni();

        $this->info("Password temporanea per {$user->name} ({$user->email}):");
        $this->line($password);
        $this->line('Link di accesso: ' . url('/admin/login?password=1'));

        return self::SUCCESS;
    }
}
