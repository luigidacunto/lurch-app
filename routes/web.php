<?php

use App\Http\Controllers\CandidaturaStaffPubblicaController;
use App\Http\Controllers\FormPreregistrazioneController;
use App\Http\Controllers\FormPreregistrazioneStaffController;
use App\Http\Controllers\GraduatoriaPubblicaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/privacy', [\App\Http\Controllers\PrivacyPubblicaController::class, 'show'])->name('privacy.show');

// Modello generale vuoto "Presa visione del Presidente" (nullaosta staff Logistica) — pubblico,
// nessun dato. La variante precompilata sta in form-staff.modello-nullaosta (con token).
Route::get('/modelli/presa-visione-staff', \App\Http\Controllers\ModelloNullaostaStaffController::class)
    ->name('modelli.presa-visione-staff');

// Guide pubbliche all'uso del portale — sorgente Markdown in docs/guide-operatori/,
// nessun auth (l'accesso al portale è esso stesso una delle guide).
Route::prefix('guide')->name('guide.')->group(function () {
    Route::get('/',            [\App\Http\Controllers\GuidaController::class, 'index'])->name('index');
    Route::get('/{slug}/pdf',  [\App\Http\Controllers\GuidaController::class, 'pdf'])->name('pdf');
    Route::get('/{slug}',      [\App\Http\Controllers\GuidaController::class, 'show'])->name('show');
});

Route::get('/admin/iscrizioni/{iscrizione}/ricevuta', \App\Http\Controllers\PreviewRicevutaController::class)
    ->middleware(['web', 'auth'])
    ->name('iscrizioni.ricevuta-preview');

Route::get('/admin/iscrizioni/{iscrizione}/nullaosta', \App\Http\Controllers\PreviewNullaostaController::class)
    ->middleware(['web', 'auth'])
    ->name('iscrizioni.nullaosta-preview');

Route::get('/admin/iscrizioni/{iscrizione}/documento-aggiuntivo', \App\Http\Controllers\PreviewDocumentoAggiuntivoController::class)
    ->middleware(['web', 'auth'])
    ->name('iscrizioni.documento-aggiuntivo-preview');

Route::get('/admin/iscrizioni-staff/{iscrizioneStaff}/nullaosta', \App\Http\Controllers\PreviewNullaostaStaffController::class)
    ->middleware(['web', 'auth'])
    ->name('iscrizioni-staff.nullaosta-preview');

Route::get('/admin/eventi/{evento}/documenti/{media}', \App\Http\Controllers\PreviewDocumentoEventoController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.documento-preview');

Route::get('/admin/graduatorie/candidati/{candidato}/attestazione', \App\Http\Controllers\PreviewAttestazionePresidenteController::class)
    ->middleware(['web', 'auth'])
    ->name('graduatoria.candidato.attestazione');

Route::get('/admin/graduatorie/candidati/{candidato}/autocertificazione', \App\Http\Controllers\PreviewAutocertificazioneController::class)
    ->middleware(['web', 'auth'])
    ->name('graduatoria.candidato.autocertificazione');

// Userscript "LURCH - GAIA Bridge": stesso URL del vecchio file statico, così le copie già
// installate continuano ad aggiornarsi. Pubblico come lo era il file.
Route::get('/tampermonkey/lurch-gaia-bridge.user.js', \App\Http\Controllers\UserscriptGaiaBridgeController::class)
    ->name('userscript.gaia-bridge');

// Bridge userscript "LURCH - GAIA Bridge" (resources/userscripts/) — chiamate same-origin,
// autenticate con la sessione web dell'utente corrente (nessun token/API key separato).
Route::prefix('api/v1')->middleware(['web', 'auth'])->group(function () {
    Route::get('/graduatorie/{graduatoria}/gaia-persona-id/da-cercare', [\App\Http\Controllers\GaiaPersonaIdController::class, 'daCercare'])
        ->name('api.graduatoria.gaia-persona-id.da-cercare');

    // Scritture su soci.gaia_persona_id: rotta inerente ai soci (non alla graduatoria),
    // autorizzazione per-socio con lo stesso gate di SocioResource::canEdit().
    Route::post('/soci/gaia-persona-id', [\App\Http\Controllers\GaiaPersonaIdController::class, 'salva'])
        ->name('api.soci.gaia-persona-id.salva');

    // Import curriculum GAIA → qualifiche_soci: stessa logica dell'import Excel
    // (QualificheSociImportParser/QualificaSocioImportWriter), analisi e conferma separate.
    Route::post('/soci/{socio}/qualifiche-gaia-analisi', [\App\Http\Controllers\GaiaCurriculumController::class, 'analisi'])
        ->name('api.soci.qualifiche-gaia-analisi');
    Route::post('/soci/{socio}/qualifiche-gaia-importa', [\App\Http\Controllers\GaiaCurriculumController::class, 'importa'])
        ->name('api.soci.qualifiche-gaia-importa');

    // Import batch multi-socio per comitato locale (pagina Qualifiche soci): stessi Services
    // del flusso singolo-socio, un parser condiviso per l'intero batch.
    Route::get('/comitati-locali/{comitatoLocale}/gaia-batch/soci', [\App\Http\Controllers\GaiaBatchImportController::class, 'soci'])
        ->name('api.comitati-locali.gaia-batch.soci');
    Route::post('/comitati-locali/{comitatoLocale}/gaia-batch/analisi', [\App\Http\Controllers\GaiaBatchImportController::class, 'analisi'])
        ->name('api.comitati-locali.gaia-batch.analisi');
    Route::post('/comitati-locali/{comitatoLocale}/gaia-batch/importa', [\App\Http\Controllers\GaiaBatchImportController::class, 'importa'])
        ->name('api.comitati-locali.gaia-batch.importa');

    // Import batch multi-socio da graduatoria (pagina Candidati, solo admin regionale/superadmin
    // — vedi User::puoUsareGaiaBridge() + Graduatoria::puoGestire()): stessa logica del batch
    // comitato, riusata via GaiaBatchImportController::eseguiAnalisiRighe()/eseguiImportazioneRighe().
    Route::get('/graduatorie/{graduatoria}/gaia-batch/soci', [\App\Http\Controllers\GaiaBatchImportController::class, 'sociGraduatoria'])
        ->name('api.graduatorie.gaia-batch.soci');
    Route::post('/graduatorie/{graduatoria}/gaia-batch/analisi', [\App\Http\Controllers\GaiaBatchImportController::class, 'analisiGraduatoria'])
        ->name('api.graduatorie.gaia-batch.analisi');
    Route::post('/graduatorie/{graduatoria}/gaia-batch/importa', [\App\Http\Controllers\GaiaBatchImportController::class, 'importaGraduatoria'])
        ->name('api.graduatorie.gaia-batch.importa');
});

Route::get('/admin/eventi/{evento}/pernotti-export', \App\Http\Controllers\ExportPernottatiController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.pernotti-export');

Route::get('/admin/eventi/{evento}/pernottanti-checkin-export', \App\Http\Controllers\ExportPernottatiCheckinController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.pernottanti-checkin-export');

Route::get('/admin/eventi/{evento}/partecipanti-export', \App\Http\Controllers\ExportPartecipantiEventoController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.partecipanti-export');

Route::get('/admin/eventi/{evento}/pasti-griglia-pdf', \App\Http\Controllers\GrigliaPastiPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.pasti-griglia-pdf');

Route::get('/admin/eventi/{evento}/pasti-dettaglio-pdf', \App\Http\Controllers\DettaglioPastiPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.pasti-dettaglio-pdf');

Route::get('/admin/eventi/{evento}/pasti-elenco-excel', \App\Http\Controllers\ElencoPastiExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.pasti-elenco-excel');

// Foglio del test scritto da stampare per un discente (BES/DSA). Mai le risposte corrette.
Route::get('/admin/esami/{esame}/valutazioni/test-scritto/{discente}/cartaceo-pdf', \App\Http\Controllers\EsameTestCartaceoPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('esami.test-cartaceo-pdf');

// Esito dell'esame. PDF per discente e CSV di tutto l'esame (provvisori finché l'esame non è chiuso).
Route::get('/admin/esami/{esame}/valutazioni/esito/{discente}/pdf', \App\Http\Controllers\EsameEsitoPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('esami.esito-pdf');
Route::get('/admin/esami/{esame}/valutazioni/esiti.csv', \App\Http\Controllers\EsameEsitiCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('esami.esiti-csv');

Route::get('/admin/corsi/{corso}/quote-pdf', \App\Http\Controllers\PreviewQuotePdfController::class)
    ->middleware(['web', 'auth'])
    ->name('corsi.quote-pdf');

Route::get('/admin/corsi/{corso}/quote-excel', \App\Http\Controllers\ExportQuoteExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('corsi.quote-excel');

Route::get('/admin/corsi/{corso}/quote-anteprima', \App\Http\Controllers\PreviewQuoteHtmlController::class)
    ->middleware(['web', 'auth'])
    ->name('corsi.quote-anteprima');

Route::get('/admin/qualifiche-soci/export-excel', \App\Http\Controllers\ExportQualificheSociExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('qualifiche-soci.export-excel');

Route::get('/admin/soci/export-excel', \App\Http\Controllers\ExportSociExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('soci.export-excel');

Route::get('/admin/qualifiche-soci/export-pdf', \App\Http\Controllers\ExportQualificheSociPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('qualifiche-soci.export-pdf');

Route::get('/admin/corsi/{corso}/discenti-excel', \App\Http\Controllers\ExportDiscentiCorsoExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('corsi.discenti-excel');

Route::get('/admin/corsi/{corso}/badge-corsisti', \App\Http\Controllers\BadgeCorsistiController::class)
    ->middleware(['web', 'auth'])
    ->name('corsi.badge-corsisti');

Route::get('/admin/graduatorie/{graduatoria}/candidati-excel', \App\Http\Controllers\ExportGraduatoriaCandidatiExcelController::class)
    ->middleware(['web', 'auth'])
    ->name('graduatorie.candidati-excel');

Route::get('/admin/graduatorie/{graduatoria}/candidati-anteprima', \App\Http\Controllers\PreviewGraduatoriaCandidatiHtmlController::class)
    ->middleware(['web', 'auth'])
    ->name('graduatorie.candidati-anteprima');

Route::get('/admin/eventi/{evento}/badge-staff', \App\Http\Controllers\BadgeStaffController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.badge-staff');

Route::get('/admin/eventi/{evento}/badge-vuoti', \App\Http\Controllers\BadgeVuotiController::class)
    ->middleware(['web', 'auth'])
    ->name('eventi.badge-vuoti');

// Portale pubblico QR badge (no auth — la logica multi-ruolo è dentro il controller)
Route::get('/badge/{evento}/{token}', \App\Http\Controllers\BadgePortaleController::class)
    ->name('badge.portale');

// Check-in: registra accesso (POST da badge.portale per iscritti/staff)
Route::post('/badge/{evento}/{token}/accesso', [\App\Http\Controllers\CheckinAccessoController::class, 'store'])
    ->middleware(['web', 'auth'])
    ->name('checkin.accesso');

// Messaggio anonimo badge (non-auth: chiunque scansioni il badge può lasciare un messaggio)
Route::post('/badge/{evento}/{token}/messaggio', [\App\Http\Controllers\MessaggioBadgeController::class, 'store'])
    ->name('badge.messaggio.store');

// Check-in: ricerca per CF + gestione ospiti
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/eventi/{evento}/checkin', [\App\Http\Controllers\CheckinRicercaController::class, 'index'])
        ->name('checkin.ricerca');
    Route::get('/admin/eventi/{evento}/checkin/suggerisci', [\App\Http\Controllers\CheckinRicercaController::class, 'suggerisci'])
        ->name('checkin.suggerisci');
    Route::post('/admin/eventi/{evento}/checkin/cerca', [\App\Http\Controllers\CheckinRicercaController::class, 'cerca'])
        ->name('checkin.cerca');
    Route::get('/admin/eventi/{evento}/checkin/ospite/{ospite}', [\App\Http\Controllers\CheckinRicercaController::class, 'ospite'])
        ->name('checkin.ospite');
    Route::post('/admin/eventi/{evento}/checkin/ospite', [\App\Http\Controllers\CheckinRicercaController::class, 'salvaOspite'])
        ->name('checkin.salva-ospite');
    Route::post('/admin/eventi/{evento}/checkin/ospite/{ospite}/accesso', [\App\Http\Controllers\CheckinRicercaController::class, 'accessoOspite'])
        ->name('checkin.accesso-ospite');
});

// whereUuid('token'): un token non-UUID non fa nemmeno match sulla rotta → 404 pulito
// invece di un 500 (QueryException: la colonna `token` è `uuid` in Postgres). Riduce la
// superficie di scansione di URL casuali su un form che espone nome/cognome/CF.
Route::prefix('form-staff')->name('form-staff.')->whereUuid('token')->group(function () {
    Route::get('/{token}',            [FormPreregistrazioneStaffController::class, 'show'])->name('show');
    Route::post('/{token}',           [FormPreregistrazioneStaffController::class, 'store'])->name('store');
    Route::get('/{token}/confermata', [FormPreregistrazioneStaffController::class, 'confermata'])->name('confermata');
    Route::post('/{token}/verifica-email', [FormPreregistrazioneStaffController::class, 'verificaEmailCodice'])->name('verifica-email');
    Route::post('/{token}/documenti/{slot}', [FormPreregistrazioneStaffController::class, 'sostituisciDocumento'])
        ->whereIn('slot', ['nullaosta'])->name('documenti.sostituisci');
    Route::get('/{token}/modello-nullaosta', \App\Http\Controllers\ModelloNullaostaStaffController::class)->name('modello-nullaosta');
});

Route::prefix('form')->name('form.')->whereUuid('token')->group(function () {
    Route::get('/{token}',  [FormPreregistrazioneController::class, 'show'])->name('show');
    Route::post('/{token}', [FormPreregistrazioneController::class, 'store'])->name('store');
    Route::get('/{token}/confermata', [FormPreregistrazioneController::class, 'confermata'])->name('confermata');
    Route::post('/{token}/verifica-email', [FormPreregistrazioneController::class, 'verificaEmailCodice'])->name('verifica-email');
    Route::post('/{token}/documenti/{slot}', [FormPreregistrazioneController::class, 'sostituisciDocumento'])
        ->whereIn('slot', ['ricevuta', 'nullaosta', 'documento_aggiuntivo'])->name('documenti.sostituisci');
});

Route::prefix('graduatoria')->name('graduatoria.')->group(function () {
    Route::get('/{slug}',           [GraduatoriaPubblicaController::class, 'mostra'])->name('mostra');
    Route::post('/{slug}/verifica', [GraduatoriaPubblicaController::class, 'verifica'])->name('verifica');
    Route::get('/{slug}/completa',  [GraduatoriaPubblicaController::class, 'completa'])->name('completa');
    Route::post('/{slug}/completa', [GraduatoriaPubblicaController::class, 'salva'])->name('salva');
    Route::post('/{slug}/forza-inserimento', [GraduatoriaPubblicaController::class, 'forzaInserimento'])->name('forza-inserimento');
    Route::get('/{slug}/grazie',    [GraduatoriaPubblicaController::class, 'grazie'])->name('grazie');
    Route::post('/{slug}/verifica-email', [GraduatoriaPubblicaController::class, 'verificaEmailCodice'])->name('verifica-email');
});

Route::prefix('candidatura-staff')->name('candidatura-staff.')->group(function () {
    Route::get('/{uuid}',           [CandidaturaStaffPubblicaController::class, 'mostra'])->name('mostra');
    Route::post('/{uuid}/verifica', [CandidaturaStaffPubblicaController::class, 'verifica'])->name('verifica');
    Route::get('/{uuid}/completa',  [CandidaturaStaffPubblicaController::class, 'completa'])->name('completa');
    Route::post('/{uuid}/completa', [CandidaturaStaffPubblicaController::class, 'salva'])->name('salva');
    Route::post('/{uuid}/forza-inserimento', [CandidaturaStaffPubblicaController::class, 'forzaInserimento'])->name('forza-inserimento');
    Route::get('/{uuid}/grazie',    [CandidaturaStaffPubblicaController::class, 'grazie'])->name('grazie');
    Route::post('/{uuid}/verifica-email', [CandidaturaStaffPubblicaController::class, 'verificaEmailCodice'])->name('verifica-email');
});

Route::get('/verifica-email/{token}', [\App\Http\Controllers\EmailVerificaController::class, 'verifica'])
    ->name('email.verifica');

// Login pannello admin senza password (magic link + codice via email)
Route::prefix('admin-login')->name('admin-login.')->group(function () {
    Route::get('/',          [\App\Http\Controllers\AdminMagicLoginController::class, 'showForm'])->name('form');
    Route::post('/',         [\App\Http\Controllers\AdminMagicLoginController::class, 'invia'])->name('invia');
    Route::get('/verifica',  [\App\Http\Controllers\AdminMagicLoginController::class, 'showVerifica'])->name('verifica');
    Route::post('/verifica', [\App\Http\Controllers\AdminMagicLoginController::class, 'verifica'])->name('verifica.post');
    Route::get('/magic/{token}', [\App\Http\Controllers\AdminMagicLoginController::class, 'magicLink'])->name('magic');
});

// Portale app PWA per soci
Route::prefix('app')->name('app.')->group(function () {
    Route::get('/login',    [\App\Http\Controllers\App\AppLoginController::class, 'showLogin'])->name('login');
    Route::post('/login',   [\App\Http\Controllers\App\AppLoginController::class, 'login'])->name('login.post');
    Route::get('/verifica', [\App\Http\Controllers\App\AppLoginController::class, 'showVerifica'])->name('verifica');
    Route::post('/verifica',[\App\Http\Controllers\App\AppLoginController::class, 'verifica'])->name('verifica.post');
    Route::post('/logout',  [\App\Http\Controllers\App\AppLoginController::class, 'logout'])->name('logout');

    Route::middleware('app.auth')->group(function () {
        Route::get('/',                                    [\App\Http\Controllers\App\AppHomeController::class,       'index'])->name('home');
        Route::get('/esami',                               [\App\Http\Controllers\App\AppEsamiController::class,      'index'])->name('esami');
        Route::get('/esami/{esame}',                       [\App\Http\Controllers\App\AppEsamiController::class,      'show'])->name('esame');
        Route::get('/esami/{esame}/itinere',                                           [\App\Http\Controllers\App\AppEsamiItinereController::class,    'elenco'])->name('esame.itinere');
        Route::get('/esami/{esame}/itinere/riepilogo',                                 [\App\Http\Controllers\App\AppEsamiRiepilogoItinereController::class, 'riepilogo'])->name('esame.itinere.riepilogo');
        Route::get('/esami/{esame}/tutorati',                                          [\App\Http\Controllers\App\AppEsamiRiepilogoItinereController::class, 'tutorati'])->name('esame.tutorati');
        Route::get('/esami/{esame}/itinere/discenti/{socio}',                        [\App\Http\Controllers\App\AppEsamiItinereController::class,    'scheda'])->name('esame.itinere.scheda');
        Route::put('/esami/{esame}/itinere/discenti/{socio}/voti',                     [\App\Http\Controllers\App\AppEsamiItinereController::class,    'salvaVoto'])->name('esame.itinere.voto');
        Route::put('/esami/{esame}/itinere/discenti/{socio}/annotazione',              [\App\Http\Controllers\App\AppEsamiItinereController::class,    'salvaAnnotazione'])->name('esame.itinere.annotazione');
        Route::get('/esami/{esame}/laboratori/{laboratorio}',                          [\App\Http\Controllers\App\AppEsamiLaboratorioController::class, 'laboratorio'])->name('esame.laboratorio');
        Route::get('/esami/{esame}/laboratori/{laboratorio}/discenti/{socio}',         [\App\Http\Controllers\App\AppEsamiLaboratorioController::class, 'scheda'])->name('esame.scheda');
        Route::put('/esami/{esame}/laboratori/{laboratorio}/discenti/{socio}/voci',    [\App\Http\Controllers\App\AppEsamiLaboratorioController::class, 'salvaVoce'])->name('esame.scheda.voce');
        Route::put('/esami/{esame}/laboratori/{laboratorio}/discenti/{socio}/annotazione', [\App\Http\Controllers\App\AppEsamiLaboratorioController::class, 'salvaAnnotazione'])->name('esame.scheda.annotazione');
        Route::post('/esami/{esame}/laboratori/{laboratorio}/discenti/{socio}/invia',  [\App\Http\Controllers\App\AppEsamiLaboratorioController::class, 'invia'])->name('esame.scheda.invia');
        // Colloquio e prova pratica (schede personali dei commissari, solo a «Apri la prova finale» aperta) + riepilogo di sola lettura
        Route::get('/esami/{esame}/colloquio',                                         [\App\Http\Controllers\App\AppEsamiColloquioController::class, 'elenco'])->name('esame.colloquio');
        Route::get('/esami/{esame}/colloquio/discenti/{socio}',                        [\App\Http\Controllers\App\AppEsamiColloquioController::class, 'scheda'])->name('esame.colloquio.scheda');
        Route::put('/esami/{esame}/colloquio/discenti/{socio}/voto',                   [\App\Http\Controllers\App\AppEsamiColloquioController::class, 'salvaVoto'])->name('esame.colloquio.voto');
        Route::put('/esami/{esame}/colloquio/discenti/{socio}/impressione',            [\App\Http\Controllers\App\AppEsamiColloquioController::class, 'salvaImpressione'])->name('esame.colloquio.impressione');
        Route::get('/esami/{esame}/prova-pratica',                                     [\App\Http\Controllers\App\AppEsamiProvaPraticaController::class, 'elenco'])->name('esame.provapratica');
        Route::get('/esami/{esame}/prova-pratica/discenti/{socio}',                    [\App\Http\Controllers\App\AppEsamiProvaPraticaController::class, 'scheda'])->name('esame.provapratica.scheda');
        Route::put('/esami/{esame}/prova-pratica/discenti/{socio}/voci',               [\App\Http\Controllers\App\AppEsamiProvaPraticaController::class, 'salvaVoce'])->name('esame.provapratica.voce');
        Route::post('/esami/{esame}/prova-pratica/discenti/{socio}/invia',             [\App\Http\Controllers\App\AppEsamiProvaPraticaController::class, 'invia'])->name('esame.provapratica.invia');
        Route::get('/esami/{esame}/prova-finale/riepilogo',                            [\App\Http\Controllers\App\AppEsamiRiepilogoProvaFinaleController::class, 'riepilogo'])->name('esame.provafinale.riepilogo');
        // Test scritto. Discente avviato: modulo, salvataggio a ogni tocco, invio. Commissione: anteprima/risultati in sola lettura.
        Route::get('/esami/{esame}/test',                                              [\App\Http\Controllers\App\AppEsamiTestController::class, 'mostra'])->name('esame.test');
        Route::put('/esami/{esame}/test/risposte',                                     [\App\Http\Controllers\App\AppEsamiTestController::class, 'salvaRisposta'])->name('esame.test.risposta');
        Route::post('/esami/{esame}/test/invia',                                       [\App\Http\Controllers\App\AppEsamiTestController::class, 'invia'])->name('esame.test.invia');
        Route::get('/esami/{esame}/test/anteprima',                                    [\App\Http\Controllers\App\AppEsamiRiepilogoTestController::class, 'anteprima'])->name('esame.test.anteprima');
        Route::get('/evento/{evento}',                    [\App\Http\Controllers\App\AppEventoController::class,     'show'])->name('evento');
        Route::get('/evento/{evento}/info',               [\App\Http\Controllers\App\AppInfoEventoController::class, 'show'])->name('evento.info');
        Route::get('/evento/{evento}/documenti/{media}',  \App\Http\Controllers\App\AppDocumentoController::class)->name('documento');

        Route::get( '/evento/{evento}/form/corsista/{iscrizione}',        [\App\Http\Controllers\App\AppFormCorsistController::class, 'show'])->name('evento.form.corsista');
        Route::post('/evento/{evento}/form/corsista/{iscrizione}',        [\App\Http\Controllers\App\AppFormCorsistController::class, 'store'])->name('evento.form.corsista.store');
        Route::post('/evento/{evento}/form/corsista/{iscrizione}/verifica-email', [\App\Http\Controllers\App\AppFormCorsistController::class, 'verificaEmailCodice'])->name('evento.form.corsista.verifica-email');
        Route::post('/evento/{evento}/form/corsista/{iscrizione}/documenti/{slot}', [\App\Http\Controllers\App\AppFormCorsistController::class, 'sostituisciDocumento'])
            ->whereIn('slot', ['ricevuta', 'nullaosta', 'documento_aggiuntivo'])->name('evento.form.corsista.documenti.sostituisci');
        Route::get( '/evento/{evento}/form/staff/{iscrizioneStaff}',      [\App\Http\Controllers\App\AppFormStaffController::class,   'show'])->name('evento.form.staff');
        Route::post('/evento/{evento}/form/staff/{iscrizioneStaff}',      [\App\Http\Controllers\App\AppFormStaffController::class,   'store'])->name('evento.form.staff.store');
        Route::post('/evento/{evento}/form/staff/{iscrizioneStaff}/verifica-email', [\App\Http\Controllers\App\AppFormStaffController::class,   'verificaEmailCodice'])->name('evento.form.staff.verifica-email');
        Route::post('/evento/{evento}/form/staff/{iscrizioneStaff}/documenti/{slot}', [\App\Http\Controllers\App\AppFormStaffController::class, 'sostituisciDocumento'])
            ->whereIn('slot', ['nullaosta'])->name('evento.form.staff.documenti.sostituisci');

        Route::get('/evento/{evento}/messaggi',                           [\App\Http\Controllers\App\AppMessaggiController::class, 'index'])->name('evento.messaggi');
        Route::get('/evento/{evento}/messaggi/{messaggio}',               [\App\Http\Controllers\App\AppMessaggiController::class, 'show'])->name('evento.messaggi.show');
    });
});
