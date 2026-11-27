<?php

use App\Jobs\CheckSovrapposizioniGlobale;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verifica notturna completa delle sovrapposizioni iscrizioni — ore 02:00
Schedule::job(new CheckSovrapposizioniGlobale)->dailyAt('02:00')
    ->name('conflitti-sovrapposizioni-globale')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('CheckSovrapposizioniGlobale: job notturno fallito.'));

// Questionario di gradimento: alla chiusura le bozze mai inviate (risposte parziali cifrate) si cancellano — ore 02:15
Artisan::command('questionari:cancella-bozze-scadute', function () {
    $this->info('Bozze cancellate: ' . \App\Questionari\CompilazioneQuestionario::cancellaBozzeScadute());
})->purpose('Cancella le bozze dei questionari di gradimento già chiusi');

Schedule::command('questionari:cancella-bozze-scadute')->dailyAt('02:15')
    ->name('questionari-cancella-bozze-scadute')
    ->withoutOverlapping();
