<?php

namespace Tests\Feature;

use App\Models\Iscrizione;
use App\Models\RispostaPreregistrazione;
use App\Services\SostituzioneDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SostituzioneDocumento: swap del file finché il documento non è verificato, blocco dopo.
 */
class SostituzioneDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function risposta(array $extra = []): RispostaPreregistrazione
    {
        return RispostaPreregistrazione::create([
            'iscrizioneable_type' => Iscrizione::class,
            'iscrizioneable_id'   => 1,
            'risposte'            => [],
            'compiled_at'         => now(),
        ] + $extra);
    }

    public function test_sostituisce_e_cancella_il_file_precedente_se_non_verificato(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('nullaosta/1/vecchio.pdf', 'vecchio');
        Storage::disk('local')->put('nullaosta/1/nuovo.pdf', 'nuovo');

        $risposta = $this->risposta(['nullaosta_path' => 'nullaosta/1/vecchio.pdf']);

        $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'nullaosta', 'nullaosta/1/nuovo.pdf');

        $this->assertTrue($ok);
        $this->assertSame('nullaosta/1/nuovo.pdf', $risposta->fresh()->nullaosta_path);
        Storage::disk('local')->assertMissing('nullaosta/1/vecchio.pdf');
        Storage::disk('local')->assertExists('nullaosta/1/nuovo.pdf');
    }

    public function test_rifiuta_e_ripulisce_l_upload_se_gia_verificato(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ricevute/1/vecchia.pdf', 'vecchia');
        Storage::disk('local')->put('ricevute/1/nuova.pdf', 'nuova');

        $risposta = $this->risposta([
            'ricevuta_path'        => 'ricevute/1/vecchia.pdf',
            'pagamento_verificato' => true,
        ]);

        $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'ricevuta', 'ricevute/1/nuova.pdf');

        $this->assertFalse($ok);
        $this->assertSame('ricevute/1/vecchia.pdf', $risposta->fresh()->ricevuta_path);
        Storage::disk('local')->assertExists('ricevute/1/vecchia.pdf');
        Storage::disk('local')->assertMissing('ricevute/1/nuova.pdf');
    }
}
