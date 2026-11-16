<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PreviewDocumentoEventoController extends Controller
{
    public function __invoke(Evento $evento, Media $media): Response
    {
        abort_unless(auth()->check(), 403);

        // Il documento deve appartenere a questo evento nella collection 'documenti'
        abort_unless(
            $media->model_type === Evento::class
            && (int) $media->model_id === $evento->id
            && $media->collection_name === 'documenti',
            404
        );

        // Stessa regola della pagina Gestione evento da cui parte il link.
        abort_unless(\App\Filament\Resources\EventoResource::canEdit($evento), 403);

        $path = $media->getPath();
        abort_unless(file_exists($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type'        => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
