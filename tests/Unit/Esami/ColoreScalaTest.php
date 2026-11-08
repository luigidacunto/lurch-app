<?php

namespace Tests\Unit\Esami;

use App\Esami\ColoreScala;
use PHPUnit\Framework\TestCase;

class ColoreScalaTest extends TestCase
{
    public function test_colloquio_0_4_con_lap_60(): void
    {
        $c = ColoreScala::per([0, 1, 2, 3, 4], 4, '60.00'); // 0, 25, 50, 75, 100 %
        $this->assertSame('hsl(0, 75%, 86%)', $c['0']);     // rosso
        $this->assertSame('hsl(28, 90%, 82%)', $c['1']);    // arancione
        $this->assertSame('hsl(50, 90%, 80%)', $c['2']);    // giallo: 50% è ancora sotto il LAP
        $this->assertSame('hsl(130, 55%, 80%)', $c['3']);   // verde: primo sufficiente
        $this->assertSame('hsl(220, 80%, 84%)', $c['4']);   // blu: eccellenza
    }

    public function test_primo_sufficiente_verde_e_oltre_verso_il_blu(): void
    {
        $c = ColoreScala::per([1, 2, 3, 4, 5], 5, 40); // 20, 40, 60, 80, 100 %
        $this->assertSame('hsl(130, 55%, 80%)', $c['2']);
        $this->assertSame('hsl(203, 80%, 84%)', $c['3']); // 1ª sopra il verde, su 3 posizioni
        $this->assertSame('hsl(220, 80%, 84%)', $c['5']);
        $this->assertSame('hsl(28, 90%, 82%)', $c['1']);  // 20%: tra LAP/3 e 2·LAP/3 → arancione

    }
}
