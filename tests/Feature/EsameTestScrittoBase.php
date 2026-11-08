<?php

namespace Tests\Feature;

use App\Esami\AvvioTest;
use App\Models\CatalogoCorso;
use App\Models\Corso;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\TestDomanda;
use App\Models\User;
use Database\Seeders\EsameModelliSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base dei test sul test scritto: esame TCRI in corso con direttore, presidente, docente di commissione,
 * docente semplice, discenti x e y (ammessi), un non ammesso, un ritirato, un estraneo + utenti del pannello.
 * DB di test: lurch_test.
 */
abstract class EsameTestScrittoBase extends TestCase
{
    use RefreshDatabase;

    /** Indici (base 0) delle risposte corrette delle 5 domande di prova: B, A, D, C, B. */
    protected const CORRETTE = [1, 0, 3, 2, 1];

    protected Esame $esame;
    protected Corso $corso;
    /** @var array<string, Socio> */
    protected array $s = [];
    /** @var array<string, User> */
    protected array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsameModelliSeeder::class);

        $now    = now();
        $cr     = DB::table('comitati_regionali')->insertGetId(['nome' => 'CR', 'created_at' => $now, 'updated_at' => $now]);
        $cl     = DB::table('comitati_locali')->insertGetId(['comitato_regionale_id' => $cr, 'nome' => 'CL', 'created_at' => $now, 'updated_at' => $now]);
        $evento = DB::table('eventi')->insertGetId([
            'comitato_regionale_id' => $cr, 'comitato_locale_id' => $cl, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
            'data_inizio' => '2026-11-01', 'data_fine' => '2026-11-02', 'giorni_evento' => '[]',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $catalogo = CatalogoCorso::create(['codice' => 'TCRI', 'nome' => 'TCRI', 'tipo' => 'formazione', 'obiettivo' => 'x', 'livello' => 'I', 'nome_visibile' => 'TCRI', 'attivo' => true]);

        foreach (['direttore', 'presidente', 'docComm', 'docente', 'x', 'y', 'nonAmmesso', 'ritirato', 'estraneo'] as $k) {
            $this->s[$k] = Socio::create(['nome' => $k . 'Nome', 'cognome' => 'Test' . $k, 'codice_fiscale' => strtoupper(substr(md5($k), 0, 16))]);
        }
        $this->corso = Corso::create([
            'evento_id' => $evento, 'nome' => 'Corso TCRI', 'numero_protocollo' => 'X/' . uniqid(),
            'data_inizio' => '2026-11-01', 'ora_inizio' => '09:00', 'data_fine' => '2026-11-02', 'ora_fine' => '18:00',
            'direttore_socio_id' => $this->s['direttore']->id, 'presidente_socio_id' => $this->s['presidente']->id,
            'catalogo_corso_id' => $catalogo->id,
        ]);
        $this->corso->docenti()->attach([$this->s['docComm']->id, $this->s['docente']->id]);
        foreach (['x', 'y', 'nonAmmesso', 'ritirato'] as $k) {
            Iscrizione::create([
                'corso_id' => $this->corso->id, 'partecipante_id' => $this->s[$k]->id, 'token' => (string) Str::uuid(),
                'stato' => $k === 'ritirato' ? 'ritirato' : 'pending',
                'non_ammesso' => $k === 'nonAmmesso', 'non_ammesso_motivo' => $k === 'nonAmmesso' ? 'assenze' : null,
            ]);
        }

        $this->esame = Esame::creaPerCorso($this->corso, 'Esame', 60);
        $this->esame->docentiCommissione()->create(['socio_id' => $this->s['docComm']->id]);
        $this->esame->attiva();
        $this->esame->refresh();

        foreach (['direttore', 'presidente', 'docComm', 'docente', 'x'] as $k) {
            $this->u[$k] = User::create(['name' => $k, 'email' => $k . '@t.it', 'password' => 'x', 'ruolo' => 'operatore', 'attivo' => true, 'socio_id' => $this->s[$k]->id]);
        }
        $this->u['super'] = User::create(['name' => 'super', 'email' => 'super@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]);
        $this->u['adminReg'] = User::create(['name' => 'ar', 'email' => 'ar@t.it', 'password' => 'x', 'ruolo' => 'admin', 'attivo' => true, 'comitato_regionale_id' => $cr]);
        $this->u['adminLoc'] = User::create(['name' => 'al', 'email' => 'al@t.it', 'password' => 'x', 'ruolo' => 'admin_locale', 'attivo' => true, 'comitato_locale_id' => $cl]);
    }

    protected function come(string $k)
    {
        return $this->withSession(['app_socio_id' => $this->s[$k]->id]);
    }

    protected function pannello(string $chi): void
    {
        $this->actingAs($this->u[$chi]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** 5 domande con 4 opzioni testuali univoche e corrette B,A,D,C,B (indici 1,0,3,2,1). */
    protected function domande(int $n = 5, ?Esame $esame = null): array
    {
        $esame ??= $this->esame;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = TestDomanda::create([
                'esame_id' => $esame->id, 'ordine' => $i + 1, 'testo' => 'Domanda numero ' . ($i + 1) . ' zz',
                'opzioni' => array_map(fn ($k) => "Opzione {$k} della domanda " . ($i + 1), [0, 1, 2, 3]),
                'corretta' => self::CORRETTE[$i % 5],
            ]);
        }

        return $out;
    }

    /** Penalità attive: corretta 2, sbagliata 1, non data 0,5 (esempio B del piano). */
    protected function conPenalita(): void
    {
        $this->esame->update(['test_penalita_attive' => true, 'test_punti_corretta' => '2.0', 'test_penalita_errata' => '1.0', 'test_penalita_vuota' => '0.5']);
        $this->esame->refresh();
    }

    protected function avvia(string ...$discenti): int
    {
        return AvvioTest::avvia($this->esame->fresh(), $this->u['direttore'], array_map(fn ($k) => $this->s[$k]->id, $discenti));
    }

    protected function token(string $discente): string
    {
        return (string) $this->esame->testAvvii()->where('discente_socio_id', $this->s[$discente]->id)->value('token');
    }

    /** PUT di una risposta come il discente. */
    protected function rispondi(string $discente, int $domandaId, ?int $opzione, ?string $token = null)
    {
        return $this->come($discente)->putJson(route('app.esame.test.risposta', $this->esame), [
            'token' => $token ?? $this->token($discente), 'domanda' => $domandaId, 'opzione' => $opzione,
        ]);
    }

    protected function invia(string $discente, ?string $token = null)
    {
        return $this->come($discente)->post(route('app.esame.test.invia', $this->esame), ['token' => $token ?? $this->token($discente)]);
    }
}
