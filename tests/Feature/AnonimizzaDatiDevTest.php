<?php

namespace Tests\Feature;

use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Comando dev:anonimizza-dati: blocco in produzione, conferma, superadmin intatto, correlazioni. DB di test: lurch_test. */
class AnonimizzaDatiDevTest extends TestCase
{
    use RefreshDatabase;

    private function socio(): Socio
    {
        return Socio::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80E12H501Z',
            'email' => 'mario@reale.it', 'telefono' => '333 1234567', 'data_nascita' => '1980-05-12',
        ]);
    }

    public function test_rifiuta_in_produzione(): void
    {
        $this->socio();
        $this->app['env'] = 'production';

        $this->artisan('dev:anonimizza-dati')->assertFailed();
        $this->assertSame('mario@reale.it', Socio::first()->email);
    }

    public function test_senza_conferma_non_modifica_nulla(): void
    {
        $this->socio();

        $this->artisan('dev:anonimizza-dati')->expectsConfirmation('Anonimizzare questi dati? Operazione NON reversibile.', 'no')->assertFailed();
        $this->assertSame('RSSMRA80E12H501Z', Socio::first()->codice_fiscale);
    }

    public function test_anonimizza_mantenendo_anno_e_superadmin(): void
    {
        $this->socio();
        $super = User::create(['ruolo' => 'superadmin', 'email' => 'admin@reale.test', 'name' => 'Admin', 'password' => 'x']);
        $op = User::create(['ruolo' => 'operatore', 'email' => 'op@reale.it', 'name' => 'Op', 'password' => 'x']);

        $this->artisan('dev:anonimizza-dati')->expectsConfirmation('Anonimizzare questi dati? Operazione NON reversibile.', 'yes')->assertSuccessful();

        $s = Socio::first();
        $this->assertNotSame('mario@reale.it', $s->email);
        $this->assertStringEndsWith('@example.invalid', $s->email);
        $this->assertSame('1980', $s->data_nascita->format('Y'));
        $this->assertStringContainsString('Z000', $s->codice_fiscale);
        $this->assertSame('admin@reale.test', $super->fresh()->email);
        $this->assertSame('Admin', $super->fresh()->name);
        $this->assertSame("operatore-{$op->id}@lurch.test", $op->fresh()->email);
    }

    public function test_utenti_gia_finti_non_vengono_ri_anonimizzati(): void
    {
        $this->socio();
        $finto = User::create(['ruolo' => 'operatore', 'email' => 'lia.segnaposto.8296@example.invalid', 'name' => 'Lia Segnaposto (N)', 'password' => 'x']);
        $reale = User::create(['ruolo' => 'operatore', 'email' => 'vero@reale.it', 'name' => 'Vero', 'password' => 'x']);

        $this->artisan('dev:anonimizza-dati')->expectsConfirmation('Anonimizzare questi dati? Operazione NON reversibile.', 'yes')->assertSuccessful();

        $this->assertSame('lia.segnaposto.8296@example.invalid', $finto->fresh()->email);
        $this->assertSame('Lia Segnaposto (N)', $finto->fresh()->name);
        $this->assertSame("operatore-{$reale->id}@lurch.test", $reale->fresh()->email);
    }

    public function test_json_risposte_ripulito_e_secondo_lancio_non_cambia_nulla(): void
    {
        $this->socio();
        \Illuminate\Support\Facades\DB::table('risposte_preregistrazione')->insert([
            'iscrizioneable_type' => 'App\Models\Iscrizione', 'iscrizioneable_id' => 1, 'compiled_at' => now(),
            'risposte' => json_encode(['email' => 'vero@reale.it', 'telefono' => '3331234567', 'note_extra' => 'chiamare Mario', 'diete' => ['Vegetariano']]),
        ]);

        $conferma = 'Anonimizzare questi dati? Operazione NON reversibile.';
        $this->artisan('dev:anonimizza-dati')->expectsConfirmation($conferma, 'yes')->assertSuccessful();

        $r = json_decode(\Illuminate\Support\Facades\DB::table('risposte_preregistrazione')->value('risposte'), true);
        $this->assertStringEndsWith('@example.invalid', $r['email']);
        $this->assertStringStartsWith('+39 000', $r['telefono']);
        $this->assertSame('Nota anonimizzata', $r['note_extra']);
        $this->assertSame(['Vegetariano'], $r['diete']);

        $dopoPrimo = [Socio::first()->only(['nome', 'cognome', 'codice_fiscale', 'email']), $r];

        $this->artisan('dev:anonimizza-dati')->expectsConfirmation($conferma, 'yes')->assertSuccessful();

        $this->assertSame($dopoPrimo[0], Socio::first()->only(['nome', 'cognome', 'codice_fiscale', 'email']));
        $this->assertSame($dopoPrimo[1], json_decode(\Illuminate\Support\Facades\DB::table('risposte_preregistrazione')->value('risposte'), true));
    }
}
