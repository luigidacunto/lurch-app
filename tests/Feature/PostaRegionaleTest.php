<?php

namespace Tests\Feature;

use App\Exceptions\PostaRegionaleNonDisponibile;
use App\Filament\Resources\CorsoResource\Pages\GestioneCorso;
use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Iscrizione;
use App\Models\Socio;
use App\Models\User;
use App\Services\PostaRegionale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Posta per regione: account del comitato regionale, blocco e ripieghi. DB di test: lurch_test (mailer array). */
class PostaRegionaleTest extends TestCase
{
    use RefreshDatabase;

    private ComitatoRegionale $conPosta;
    private ComitatoRegionale $senzaPosta;

    /** @var list<\Symfony\Component\Mime\Email> */
    private array $inviate = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'generale@lurch.example', 'mail.from.name' => 'LURCH']);

        $this->conPosta = ComitatoRegionale::create([
            'nome' => 'Regione Con Posta', 'codice' => 'RCP',
            'mail_host' => 'smtp.regione.example', 'mail_port' => 587, 'mail_username' => 'utente',
            'mail_password' => 'segreta', 'mail_from_address' => 'lurch@regione.example', 'mail_from_name' => 'CRI Regione',
        ]);
        $this->senzaPosta = ComitatoRegionale::create(['nome' => 'Regione Senza Posta', 'codice' => 'RSP']);

        Event::listen(MessageSent::class, fn (MessageSent $e) => $this->inviate[] = $e->message);
    }

    private function mittente(): string
    {
        return end($this->inviate)->getFrom()[0]->getAddress();
    }

    public function test_mail_di_evento_con_l_account_della_regione_o_bloccate(): void
    {
        PostaRegionale::perEvento($this->conPosta->id)->raw('prova', fn ($m) => $m->to('a@b.example'));
        $this->assertSame('lurch@regione.example', $this->mittente());

        $this->expectException(PostaRegionaleNonDisponibile::class);
        PostaRegionale::perEvento($this->senzaPosta->id);
    }

    public function test_accessi_con_l_account_della_regione_altrimenti_quello_generale(): void
    {
        PostaRegionale::perPersona($this->conPosta->id)->raw('prova', fn ($m) => $m->to('a@b.example'));
        $this->assertSame('lurch@regione.example', $this->mittente());

        $this->assertSame(Mail::mailer(), PostaRegionale::perPersona($this->senzaPosta->id));
        $this->assertSame(Mail::mailer(), PostaRegionale::perPersona(null));
    }

    public function test_password_illeggibile_blocca_gli_eventi_e_ripiega_per_gli_accessi(): void
    {
        DB::table('comitati_regionali')->where('id', $this->conPosta->id)->update(['mail_password' => 'cifrata-altrove']);

        $this->assertSame(Mail::mailer(), PostaRegionale::perPersona($this->conPosta->id));

        $this->expectExceptionMessage('password SMTP salvata non è leggibile');
        PostaRegionale::perEvento($this->conPosta->id);
    }

    public function test_gestione_corso_blocca_l_invio_senza_account_e_usa_quello_della_regione(): void
    {
        $this->actingAs(User::create(['name' => 's', 'email' => 's@t.it', 'password' => 'x', 'ruolo' => 'superadmin', 'attivo' => true]));

        foreach ([$this->senzaPosta, $this->conPosta] as $regione) {
            $now = now();
            $evento = DB::table('eventi')->insertGetId([
                'comitato_regionale_id' => $regione->id, 'nome' => 'Evento', 'sede' => 's', 'indirizzo' => 'i',
                'data_inizio' => now()->addDays(10)->toDateString(), 'data_fine' => now()->addDays(11)->toDateString(),
                'giorni_evento' => '[]', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $corso = Corso::create([
                'evento_id' => $evento, 'nome' => 'Corso', 'numero_protocollo' => 'X/' . Str::random(6), 'direttore_corso' => 'Dir',
                'data_inizio' => now()->addDays(10)->toDateString(), 'ora_inizio' => '09:00',
                'data_fine' => now()->addDays(11)->toDateString(), 'ora_fine' => '18:00',
                'form_sections' => [['tipo' => 'dati']],
            ]);
            $socio = Socio::create(['nome' => 'Mario', 'cognome' => 'Prova', 'codice_fiscale' => strtoupper(Str::random(16)), 'email' => 'mario@example.invalid']);
            $iscrizione = Iscrizione::create(['corso_id' => $corso->id, 'partecipante_id' => $socio->id, 'token' => (string) Str::uuid(), 'stato' => 'pending']);

            Livewire::test(GestioneCorso::class, ['record' => $corso])
                ->callAction('inviaFormSingolo', arguments: ['iscrizione_id' => $iscrizione->id]);

            if ($regione->is($this->senzaPosta)) {
                $this->assertSame([], $this->inviate, 'Mail partita senza account della regione');
                $this->assertNull($iscrizione->refresh()->form_inviato_at, 'Form segnato come inviato senza invio');
            } else {
                $this->assertCount(1, $this->inviate);
                $this->assertSame('lurch@regione.example', $this->mittente());
                $this->assertNotNull($iscrizione->refresh()->form_inviato_at);
            }
        }
    }
}
