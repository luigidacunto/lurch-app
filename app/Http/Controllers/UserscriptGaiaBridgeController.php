<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serve lo userscript "LURCH - GAIA Bridge" con l'indirizzo di questa installazione al posto
 * del segnaposto __LURCH_URL__ (@namespace, @match, @updateURL, @downloadURL): ogni sito
 * distribuisce e aggiorna la propria copia. Sorgente in resources/userscripts/, fuori da
 * public/ perché il file statico avrebbe la precedenza sulla rotta.
 */
class UserscriptGaiaBridgeController extends Controller
{
    public function __invoke(): Response
    {
        $sorgente = file_get_contents(resource_path('userscripts/lurch-gaia-bridge.user.js'));

        return response(str_replace('__LURCH_URL__', rtrim(url('/'), '/'), $sorgente), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
