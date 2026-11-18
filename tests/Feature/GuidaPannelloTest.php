<?php

namespace Tests\Feature;

use App\Filament\Pages\Guida\GuidaAppSoci;
use App\Filament\Pages\Guida\GuidaPannello;
use App\Models\User;
use App\Support\Guida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Guida nel pannello: legge i file reali di docs/. */
class GuidaPannelloTest extends TestCase
{
    use RefreshDatabase;

    private function operatore(): User
    {
        return User::create([
            'name' => 'Operatore', 'email' => 'operatore@example.invalid', 'password' => 'x',
            'ruolo' => 'operatore', 'attivo' => true,
        ]);
    }

    public function test_indice_segue_cartelle_e_numeri(): void
    {
        $indice = Guida::indice('01-pannello');

        $this->assertSame('Primi passi', $indice[0]['titolo']);
        $this->assertSame('01-primi-passi/01-accesso', $indice[0]['pagine'][0]['percorso']);
        $this->assertSame('Gestione operativa', $indice[1]['titolo']);
    }

    public function test_percorsi_non_validi_non_escono_dalla_cartella(): void
    {
        foreach (['../../.env', '01-primi-passi/../../README', 'x', '01-primi-passi/99-inesistente'] as $percorso) {
            $this->assertNull(Guida::pagina('01-pannello', $percorso), $percorso);
        }
        foreach (['immagini/../../.env', 'immagini/accesso/../../../composer.json', 'immagini/x.php', 'README.md'] as $percorso) {
            $this->assertNull(Guida::fileImmagine($percorso), $percorso);
        }
    }

    public function test_link_e_immagini_diventano_indirizzi_del_pannello(): void
    {
        config(['app.guida_url' => 'https://example.invalid/docs']);

        $indice = Guida::pagina('01-pannello', null)['html'];
        $this->assertStringContainsString('href="' . e(GuidaPannello::getUrl(['p' => '01-primi-passi/01-accesso'])) . '"', $indice);
        $this->assertStringContainsString('href="' . e(GuidaAppSoci::getUrl()) . '"', $indice);
        $this->assertStringContainsString('href="https://example.invalid/docs/00-installazione/README.md"', $indice);

        $accesso = Guida::pagina('01-pannello', '01-primi-passi/01-accesso');
        $this->assertStringContainsString('src="' . route('guida.immagine', ['percorso' => 'accesso/pagina-login.png', 'v' => filemtime(Guida::radice() . '/immagini/accesso/pagina-login.png')]) . '"', $accesso['html']);
        $this->assertStringContainsString('<h2 id="problemi-frequenti">', $accesso['html']);
        $this->assertContains('Problemi frequenti', array_column($accesso['paragrafi'], 'testo'));
    }

    /** Ogni link relativo e ogni immagine dei .md di docs/ punta a un file che esiste (vale per GitHub e pannello). */
    public function test_nessun_link_rotto_in_docs(): void
    {
        $file = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Guida::radice(), \FilesystemIterator::SKIP_DOTS));
        $rotti = [];
        foreach ($file as $md) {
            if ($md->getExtension() !== 'md') {
                continue;
            }
            preg_match_all('#!?\[[^\]]*\]\(([^)\s]+)\)#', file_get_contents($md->getPathname()), $link);
            foreach ($link[1] as $destinazione) {
                if (preg_match('#^([a-z][a-z0-9+.-]*:|/|\#)#i', $destinazione)) {
                    continue;
                }
                $percorso = $md->getPath() . '/' . explode('#', $destinazione)[0];
                if (! file_exists($percorso)) {
                    $rotti[] = str_replace(Guida::radice(), 'docs', $md->getPathname()) . ' → ' . $destinazione;
                }
            }
        }

        $this->assertSame([], $rotti);
    }

    public function test_un_operatore_apre_la_guida_le_immagini_e_il_pdf(): void
    {
        $operatore = $this->operatore();

        $this->actingAs($operatore)->get(GuidaPannello::getUrl())->assertOk()->assertSee('Primi passi')->assertSee('Scarica PDF');
        $this->actingAs($operatore)->get(GuidaPannello::getUrl(['p' => '02-gestione-operativa/01-eventi/03-staff']))
            ->assertOk()->assertSee('Reset form');
        $this->actingAs($operatore)->get(GuidaPannello::getUrl(['p' => '02-gestione-operativa/01-eventi/README']))
            ->assertOk()->assertSee('Presenze live');
        $this->actingAs($operatore)->get(GuidaPannello::getUrl(['p' => '../segreto']))->assertNotFound();

        $this->actingAs($operatore)->get(route('guida.immagine', ['percorso' => 'accesso/pagina-login.png']))->assertOk();
        $this->actingAs($operatore)->get('/admin/guida-file/immagini/../../.env')->assertNotFound();

        $pdf = $this->actingAs($operatore)->get(route('guida.pdf', '01-pannello'));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($operatore)->get(route('guida.pdf', '00-installazione'))->assertNotFound();
    }

    public function test_senza_login_niente_immagini_ne_pdf(): void
    {
        // Vale per tutte le rotte ['web', 'auth'] di web.php (bootstrap/app.php → redirectGuestsTo).
        $this->get(route('guida.immagine', ['percorso' => 'accesso/pagina-login.png']))->assertRedirect(route('admin-login.form'));
        $this->get(route('guida.pdf', '01-pannello'))->assertRedirect(route('admin-login.form'));
        $this->postJson('/api/v1/soci/gaia-persona-id')->assertUnauthorized();
    }
}
