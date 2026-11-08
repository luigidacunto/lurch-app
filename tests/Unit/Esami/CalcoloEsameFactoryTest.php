<?php

declare(strict_types=1);

namespace Tests\Unit\Esami;

use App\Esami\Calcolo\CalcoloEsameFactory;
use App\Esami\Calcolo\CalcoloTcri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalcoloEsameFactoryTest extends TestCase
{
    public function test_tcri_restituisce_il_calcolo_tcri(): void
    {
        $this->assertInstanceOf(CalcoloTcri::class, CalcoloEsameFactory::per('TCRI'));
    }

    public function test_tipo_sconosciuto_lancia_eccezione(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalcoloEsameFactory::per('XYZ');
    }
}
