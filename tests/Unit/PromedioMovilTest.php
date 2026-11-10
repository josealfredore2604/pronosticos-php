<?php

declare(strict_types=1);

namespace Pronosticos\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pronosticos\CalculadoraPronosticos;

/**
 * Pruebas unitarias de promedioMovil.
 *
 * Cada prueba sigue el patrón Arrange-Act-Assert (preparar, ejecutar, verificar)
 * y revisa un solo comportamiento. Los valores esperados salen de un cálculo
 * independiente hecho a mano, nunca del propio código que se está probando.
 */
final class PromedioMovilTest extends TestCase
{
    private const SERIE = [120, 132, 128, 141, 138, 150];

    private CalculadoraPronosticos $calculadora;

    // setUp se ejecuta antes de cada prueba: cada una arranca con un objeto nuevo.
    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraPronosticos();
    }

    public function testPronosticaConElPromedioDeLosUltimosPeriodos(): void
    {
        $resultado = $this->calculadora->promedioMovil(self::SERIE, 3);

        // (141 + 138 + 150) / 3 = 143. El tercer argumento es la tolerancia.
        $this->assertEqualsWithDelta(143.00, $resultado, 0.01);
    }

    public function testConVentanaIgualALaSerieUsaTodosLosPeriodos(): void
    {
        $resultado = $this->calculadora->promedioMovil([100, 110, 120], 3);

        $this->assertEqualsWithDelta(110.00, $resultado, 0.01);
    }

    public function testConVentanaDeUnPeriodoRepiteElUltimoValor(): void
    {
        $resultado = $this->calculadora->promedioMovil(self::SERIE, 1);

        $this->assertEqualsWithDelta(150.00, $resultado, 0.01);
    }

    public function testRechazaUnaVentanaMayorQueLaSerie(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('menos periodos que la ventana');

        $this->calculadora->promedioMovil([100, 110], 3);
    }

    public function testRechazaUnaVentanaQueNoEsPositiva(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entero positivo');

        $this->calculadora->promedioMovil(self::SERIE, 0);
    }
}
