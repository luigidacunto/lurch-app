<?php

namespace App\Esami;

use RuntimeException;

/** Rifiuto di un'operazione sul test scritto, con il codice HTTP da restituire (403/409/422) e un messaggio per l'utente. */
class TestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $stato = 409)
    {
        parent::__construct($message);
    }
}
