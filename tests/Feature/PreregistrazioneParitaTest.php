<?php

namespace Tests\Feature;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Corso;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\RispostaPreregistrazione;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parità form pubblico ↔ PWA: pagamento, nullaosta ed email obbligatoria devono valere
 * identici sui due canali (stessa logica in PreregistrazioneCorsistaHandler). + `updated_by`.
 */
class PreregistrazioneParitaTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function comitato(): ComitatoLocale
    {
        self::$seq++;
        $reg = ComitatoRegionale::create(['nome' => "Reg " . self::$seq, 'codice' => "R" . self::$seq, 'attivo' => true]);
        return ComitatoLocale::create(['comitato_regionale_id' => $reg->id, 'nome' => "Loc " . self::$seq, 'codice' => "L" . self::$seq, 'attivo' => true]);
    }

    /** Evento + corso a pagamento con una sezione pasto; iscrizione di un socio di regione diversa. */
    private function scenario(): array
    {
        $locEvento = $this->comitato();
        $locSocio  = $this->comitato(); // regione diversa → nullaosta richiesto

        $evento = Evento::create([
            'nome'                  => 'Evento Test',
            'comitato_regionale_id' => $locEvento->comitato_regionale_id,
            'comitato_locale_id'    => $locEvento->id,
            'data_inizio'           => '2026-10-24',
            'data_fine'             => '2026-10-25',
            'giorni_evento'         => ['2026-10-24', '2026-10-25'],
            'sede'                  => 'Sede',
            'indirizzo'             => 'Via Test 1',
        ]);

        $corso = Corso::create([
            'evento_id'            => $evento->id,
            'nome'                 => 'Corso Test',
            'numero_protocollo'    => 'TEST/' . self::$seq,
            'data_inizio'          => '2026-10-24',
            'data_fine'            => '2026-10-25',
            'ora_inizio'           => '09:00',
            'ora_fine'             => '18:00',
            'residenziale'         => false,
            'pagamento_previsto'   => true,
            'quota_partecipazione' => 30,
            'giorni_presenza'      => ['2026-10-24'],
            'form_sections'        => [
                ['tipo' => 'pranzo', 'data' => '2026-10-24', 'label' => 'Pranzo', 'attivo' => true, 'obbligatorio' => false],
            ],
        ]);

        $socio = Socio::create([
            'nome'                  => 'Mario',
            'cognome'               => 'Rossi',
            'codice_fiscale'        => str_pad((string) self::$seq, 16, 'X'),
            'comitato_regionale_id' => $locSocio->comitato_regionale_id,
            'comitato_locale_id'    => $locSocio->id,
            'email'                 => 'mario' . self::$seq . '@test.local',
            'email_verificata_at'   => now(),
        ]);

        $iscrizione = Iscrizione::create([
            'corso_id'        => $corso->id,
            'partecipante_id' => $socio->id,
            'stato'           => 'pending',
            'token'           => (string) Str::uuid(),
        ]);

        return compact('evento', 'corso', 'socio', 'iscrizione', 'locSocio');
    }

    private function payloadValido(array $s): array
    {
        return [
            'comitato_regionale_id'     => $s['locSocio']->comitato_regionale_id,
            'comitato_locale_id'        => $s['locSocio']->id,
            'email'                     => $s['socio']->email,
            'privacy_accettata'         => '1',
            'consenso_gaia_accettata'   => '1',
            'foto_video_accettata'      => '1',
        ];
    }

    // ─── PUBBLICO ────────────────────────────────────────────────────────────

    public function test_pubblico_blocca_senza_ricevuta_e_data_pagamento(): void
    {
        $s = $this->scenario();

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadValido($s) + [
            'nullaosta' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['ricevuta', 'data_pagamento']);
    }

    public function test_pubblico_blocca_senza_nullaosta_regione_diversa(): void
    {
        $s = $this->scenario();

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadValido($s) + [
            'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
            'data_pagamento' => '2026-09-01',
        ])->assertSessionHasErrors('nullaosta');
    }

    public function test_pubblico_salva_fonte_pubblico(): void
    {
        $s = $this->scenario();

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadValido($s) + [
            'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
            'data_pagamento' => '2026-09-01',
            'nullaosta'      => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $r = RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id);
        $this->assertSame('pubblico', $r->fonte_compilazione);
        $this->assertNotNull($r->ricevuta_path);
        $this->assertNotNull($r->nullaosta_path);
    }

    // ─── PWA ─────────────────────────────────────────────────────────────────

    public function test_pwa_blocca_senza_ricevuta_e_data_pagamento(): void
    {
        $s = $this->scenario();

        $this->withSession(['app_socio_id' => $s['socio']->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]), $this->payloadValido($s) + [
                'nullaosta' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors(['ricevuta', 'data_pagamento']);
    }

    public function test_pwa_blocca_senza_nullaosta_regione_diversa(): void
    {
        $s = $this->scenario();

        $this->withSession(['app_socio_id' => $s['socio']->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]), $this->payloadValido($s) + [
                'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
                'data_pagamento' => '2026-09-01',
            ])->assertSessionHasErrors('nullaosta');
    }

    public function test_pwa_blocca_senza_email(): void
    {
        $s = $this->scenario();

        $this->withSession(['app_socio_id' => $s['socio']->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]),
                array_diff_key($this->payloadValido($s), ['email' => 1]) + [
                    'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
                    'data_pagamento' => '2026-09-01',
                    'nullaosta'      => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
                ])->assertSessionHasErrors('email');
    }

    public function test_pwa_salva_fonte_pwa(): void
    {
        $s = $this->scenario();

        $this->withSession(['app_socio_id' => $s['socio']->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]), $this->payloadValido($s) + [
                'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
                'data_pagamento' => '2026-09-01',
                'nullaosta'      => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $this->assertSame('pwa', RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id)->fonte_compilazione);
    }

    public function test_pwa_403_se_iscrizione_di_altro_socio(): void
    {
        $s = $this->scenario();
        $altro = $this->comitato();
        $altroSocio = Socio::create([
            'nome' => 'X', 'cognome' => 'Y', 'codice_fiscale' => str_pad('9' . self::$seq, 16, 'Z'),
            'comitato_regionale_id' => $altro->comitato_regionale_id, 'comitato_locale_id' => $altro->id,
        ]);

        $this->withSession(['app_socio_id' => $altroSocio->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]), $this->payloadValido($s))
            ->assertForbidden();
    }

    // ─── updated_by ──────────────────────────────────────────────────────────

    public function test_updated_by_popolato_solo_con_auth(): void
    {
        $s = $this->scenario();

        $s['corso']->update(['nome' => 'Senza auth']);
        $this->assertNull($s['corso']->fresh()->updated_by);

        $u = User::create([
            'name' => 'Admin Test', 'email' => 'admin' . self::$seq . '@test.local',
            'ruolo' => 'superadmin', 'attivo' => true,
        ]);
        $this->actingAs($u);
        $s['corso']->update(['nome' => 'Con auth']);
        $this->assertSame($u->id, $s['corso']->fresh()->updated_by);
    }

    // ─── Sostituzione documenti (finché non verificati) ──────────────────────

    /** Compila il form pubblico così che esista una RispostaPreregistrazione con i due file. */
    private function compilaForm(array $s): RispostaPreregistrazione
    {
        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadValido($s) + [
            'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
            'data_pagamento' => '2026-09-01',
            'nullaosta'      => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id);
    }

    public function test_pubblico_sostituisce_ricevuta_se_non_verificata(): void
    {
        $s = $this->scenario();
        $r = $this->compilaForm($s);
        $vecchio = $r->ricevuta_path;

        $this->post(route('form.documenti.sostituisci', [$s['iscrizione']->token, 'ricevuta']), [
            'documento' => UploadedFile::fake()->create('nuova.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('form.confermata', $s['iscrizione']->token))
          ->assertSessionHas('doc_sostituito');

        $this->assertNotSame($vecchio, $r->fresh()->ricevuta_path);
    }

    public function test_pubblico_non_sostituisce_ricevuta_verificata(): void
    {
        $s = $this->scenario();
        $r = $this->compilaForm($s);
        $r->update(['pagamento_verificato' => true]);
        $atteso = $r->ricevuta_path;

        $this->post(route('form.documenti.sostituisci', [$s['iscrizione']->token, 'ricevuta']), [
            'documento' => UploadedFile::fake()->create('nuova.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('doc_errore');

        $this->assertSame($atteso, $r->fresh()->ricevuta_path);
    }

    public function test_pwa_sostituzione_403_se_iscrizione_di_altro_socio(): void
    {
        $s = $this->scenario();
        $this->compilaForm($s);

        $altro = $this->comitato();
        $altroSocio = Socio::create([
            'nome' => 'X', 'cognome' => 'Y', 'codice_fiscale' => str_pad('8' . self::$seq, 16, 'Z'),
            'comitato_regionale_id' => $altro->comitato_regionale_id, 'comitato_locale_id' => $altro->id,
        ]);

        $this->withSession(['app_socio_id' => $altroSocio->id])
            ->post(route('app.evento.form.corsista.documenti.sostituisci', [$s['evento'], $s['iscrizione'], 'ricevuta']), [
                'documento' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])->assertForbidden();
    }

    // ─── Documento aggiuntivo richiesto dal corso ────────────────────────────

    private function payloadCompleto(array $s, array $extra = []): array
    {
        return $this->payloadValido($s) + $extra + [
            'ricevuta'       => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
            'data_pagamento' => '2026-09-01',
            'nullaosta'      => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
        ];
    }

    private function richiediDocumento(array $s): void
    {
        $s['corso']->update(['richiede_documento_aggiuntivo' => true, 'dicitura_documento_aggiuntivo' => 'Titolo di studio']);
    }

    public function test_documento_aggiuntivo_obbligatorio_su_pubblico_e_pwa(): void
    {
        $s = $this->scenario();
        $this->richiediDocumento($s);

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadCompleto($s))
            ->assertSessionHasErrors('documento_aggiuntivo');

        $this->withSession(['app_socio_id' => $s['socio']->id])
            ->post(route('app.evento.form.corsista.store', [$s['evento'], $s['iscrizione']]), $this->payloadCompleto($s))
            ->assertSessionHasErrors('documento_aggiuntivo');
    }

    public function test_documento_aggiuntivo_solo_pdf(): void
    {
        $s = $this->scenario();
        $this->richiediDocumento($s);

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadCompleto($s, [
            'documento_aggiuntivo' => UploadedFile::fake()->create('t.jpg', 10, 'image/jpeg'),
        ]))->assertSessionHasErrors('documento_aggiuntivo');
    }

    public function test_documento_aggiuntivo_non_richiesto_se_switch_spento(): void
    {
        $s = $this->scenario();

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadCompleto($s))
            ->assertSessionHasNoErrors();
        $this->assertNull(RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id)->documento_aggiuntivo_path);
    }

    public function test_documento_aggiuntivo_salvato_con_il_form(): void
    {
        $s = $this->scenario();
        $this->richiediDocumento($s);

        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadCompleto($s, [
            'documento_aggiuntivo' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->assertNotNull(RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id)->documento_aggiuntivo_path);
    }

    public function test_documento_aggiuntivo_attivato_dopo_la_compilazione_si_carica_dal_link(): void
    {
        $s = $this->scenario();
        $r = $this->compilaForm($s);
        $this->assertNull($r->documento_aggiuntivo_path);

        $this->richiediDocumento($s);

        $this->get(route('form.confermata', $s['iscrizione']->token))
            ->assertOk()->assertSee('Da caricare')->assertSee('Titolo di studio');

        $this->post(route('form.documenti.sostituisci', [$s['iscrizione']->token, 'documento_aggiuntivo']), [
            'documento' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('doc_sostituito');

        $this->assertNotNull($r->fresh()->documento_aggiuntivo_path);
    }

    public function test_documento_aggiuntivo_slot_rifiutato_se_corso_non_lo_richiede(): void
    {
        $s = $this->scenario();
        $r = $this->compilaForm($s);

        $this->post(route('form.documenti.sostituisci', [$s['iscrizione']->token, 'documento_aggiuntivo']), [
            'documento' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('doc_errore');

        $this->assertNull($r->fresh()->documento_aggiuntivo_path);
    }

    public function test_documento_aggiuntivo_verificato_non_sostituibile(): void
    {
        $s = $this->scenario();
        $this->richiediDocumento($s);
        $this->post(route('form.store', $s['iscrizione']->token), $this->payloadCompleto($s, [
            'documento_aggiuntivo' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasNoErrors();
        $r = RispostaPreregistrazione::firstWhere('iscrizioneable_id', $s['iscrizione']->id);
        $r->update(['documento_aggiuntivo_verificato' => true]);
        $atteso = $r->documento_aggiuntivo_path;

        $this->post(route('form.documenti.sostituisci', [$s['iscrizione']->token, 'documento_aggiuntivo']), [
            'documento' => UploadedFile::fake()->create('nuovo.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('doc_errore');

        $this->assertSame($atteso, $r->fresh()->documento_aggiuntivo_path);
    }
}
