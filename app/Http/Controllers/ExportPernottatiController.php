<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Presenza;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportPernottatiController extends Controller
{
    public function __invoke(Evento $evento): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $pernottati = Presenza::where('evento_id', $evento->id)
            ->where('tipo', 'pernotto')
            ->with([
                'partecipante:id,nome,cognome,codice_fiscale,email,prefisso_telefono,telefono',
                'iscrizione.corso:id,nome',
            ])
            ->get()
            ->groupBy('partecipante_id')
            ->map(function ($gruppo) {
                $primo = $gruppo->first();
                $p     = $primo->partecipante;
                return [
                    'cognome'        => $p?->cognome ?? '',
                    'nome'           => $p?->nome ?? '',
                    'codice_fiscale' => $p?->codice_fiscale ?? '',
                    'email'          => $p?->email ?? '',
                    'telefono'       => trim(($p?->prefisso_telefono ?? '') . ' ' . ($p?->telefono ?? '')),
                    'corso'          => $primo->iscrizione?->corso?->nome ?? '',
                    'notti'          => $gruppo->count(),
                ];
            })
            ->sortBy(fn ($r) => $r['cognome'] . ' ' . $r['nome'])
            ->values();

        $nomeFile = 'pernottati_' . \Illuminate\Support\Str::slug($evento->nome) . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($pernottati) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 per Excel
            fputcsv($handle, ['Cognome', 'Nome', 'Codice Fiscale', 'Email', 'Telefono', 'Corso', 'N. notti']);
            foreach ($pernottati as $row) {
                fputcsv($handle, [
                    $row['cognome'],
                    $row['nome'],
                    $row['codice_fiscale'],
                    $row['email'],
                    $row['telefono'],
                    $row['corso'],
                    $row['notti'],
                ]);
            }
            fclose($handle);
        }, $nomeFile, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
