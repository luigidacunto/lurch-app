<?php

namespace App\Filament\Concerns;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * In Filament 5 le pagine della risorsa (Nuovo, Modifica, Visualizza) e le azioni Elimina
 * autorizzano tramite get*AuthorizationResponse(), cioè le policy di Laravel: senza policy
 * lasciano passare. I canCreate()/canEdit()/canView()/canDelete() ridefiniti nelle risorse
 * nasconderebbero solo i pulsanti, e l'indirizzo diretto aprirebbe comunque la pagina.
 * Questo tratto fa passare l'autorizzazione dai metodi can*() della risorsa, quando la
 * risorsa li ridefinisce; altrimenti resta il comportamento standard di Filament (il
 * controllo sulla classe che dichiara il metodo evita la ricorsione con i can*() di base,
 * che a loro volta chiamano get*AuthorizationResponse()).
 */
trait RispettaPermessiDellaRisorsa
{
    public static function getCreateAuthorizationResponse(): Response
    {
        if (! static::ridefinisce('canCreate')) {
            return parent::getCreateAuthorizationResponse();
        }

        return static::canCreate() ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! static::ridefinisce('canEdit')) {
            return parent::getEditAuthorizationResponse($record);
        }

        return static::canEdit($record) ? Response::allow() : Response::deny();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        if (! static::ridefinisce('canView')) {
            return parent::getViewAuthorizationResponse($record);
        }

        return static::canView($record) ? Response::allow() : Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! static::ridefinisce('canDelete')) {
            return parent::getDeleteAuthorizationResponse($record);
        }

        return static::canDelete($record) ? Response::allow() : Response::deny();
    }

    private static function ridefinisce(string $metodo): bool
    {
        return (new \ReflectionMethod(static::class, $metodo))->getDeclaringClass()->getName() !== Resource::class;
    }
}
