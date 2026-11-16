<?php

namespace App\Exceptions;

use RuntimeException;

/** La regione non ha un account di posta utilizzabile: il messaggio è pensato per l'operatore. */
class PostaRegionaleNonDisponibile extends RuntimeException
{
}
