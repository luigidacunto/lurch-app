<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppDocumentoController extends Controller
{
    public function __invoke(Request $request, Evento $evento, Media $media): Response
    {
        $socioId = $request->session()->get('app_socio_id');

        abort_unless(
            $media->model_type === Evento::class
            && (int) $media->model_id === $evento->id
            && $media->collection_name === 'documenti',
            404
        );

        $hasAccess = Iscrizione::where('partecipante_id', $socioId)
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->exists()
            || IscrizioneStaff::where('partecipante_id', $socioId)
            ->where('evento_id', $evento->id)
            ->exists();

        abort_unless($hasAccess, 403);

        $path = $media->getPath();
        abort_unless(file_exists($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type'        => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
