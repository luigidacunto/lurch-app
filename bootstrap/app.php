<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'app.auth' => \App\Http\Middleware\AppSocioAuth::class,
        ]);
        // Rotte ['web', 'auth'] di web.php (download e anteprime del pannello): chi non è entrato va
        // all'accesso via email. Il pannello Filament usa il proprio middleware; le richieste JSON ricevono 401.
        $middleware->redirectGuestsTo(fn () => route('admin-login.form'));
        // Globale: vale anche per il pannello Filament e gli aggiornamenti Livewire (non usano il gruppo web).
        $middleware->append(\App\Http\Middleware\UrlFilePubbliciSulDominio::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
