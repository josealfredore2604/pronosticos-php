<?php

declare(strict_types=1);

namespace Pronosticos\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pronosticos\CalculadoraPronosticos;

/** Pruebas unitarias del error absoluto medio (MAD). */
final class MadTest extends TestCase
{
    private CalculadoraPronosticos $calculadora;

    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraPronosticos();
    }

    public function testCalculaElErrorAbsolutoMedio(): void
    {
        $resultado = $this->calculadora->mad([141, 138, 150], [126.67, 133.67, 135.67]);

        // (14.33 + 4.33 + 14.33) / 3 = 11.00, calculado de forma independiente.
        // Los decimales se comparan con tolerancia, nunca con igualdad exacta.
        $this->assertEqualsWithDelta(11.00, $resultado, 0.01);
    }

    public function testEsCeroCuandoElPronosticoEsExacto(): void
    {
        $this->assertSame(0.0, $this->calculadora->mad([100, 100], [100, 100]));
    }

    public function testRechazaSeriesDeDistintaLongitud(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mismo número de periodos');

        $this->calculadora->mad([100, 110], [100]);
    }

    public function testRechazaSeriesVacias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('al menos un periodo');

        $this->calculadora->mad([], []);
    }
}
