<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;
use Pronosticos\CalculadoraPronosticos;

/**
 * Pasos BDD del pronóstico con promedio móvil.
 *
 * Conecta cada frase de features/promedio_movil.feature con código. El escenario
 * describe el comportamiento con las palabras de Producción; aquí solo se traduce
 * cada frase a una llamada a la calculadora y a una verificación.
 *
 * Behat solo usa los contextos registrados en behat.yml. Cada característica del
 * sistema tiene su propio contexto para que ninguna clase crezca sin control.
 */
final class PromedioMovilContext implements Context
{
    /** @var list<float> */
    private array $serie = [];
    private ?float $pronostico = null;
    private ?InvalidArgumentException $error = null;

    // Behat crea un contexto nuevo para cada escenario: las propiedades empiezan
    // vacías y ningún escenario depende del resultado de otro.

    /**
     * Behat reconoce el paso por el texto, no por la palabra clave: "Dada" y "Dado"
     * corresponden a #[Given]. Se usa una expresión regular porque la serie es una
     * lista separada por comas, que un marcador como :serie no captura completa.
     */
    #[Given('/^la serie de demanda (?<serie>[0-9., ]+)$/')]
    public function laSerieDeDemanda(string $serie): void
    {
        $this->serie = array_map('floatval', explode(',', $serie));
    }

    /**
     * :ventana captura el número de la frase. En el esquema, <ventana> ya fue
     * reemplazado por el valor de la fila (7 o 0), así que este paso sirve para todas.
     *
     * El paso "Cuando" solo ejecuta la acción y guarda lo que pasó: el resultado o
     * la excepción. Decidir si eso es correcto le corresponde al paso "Entonces".
     */
    #[When('calculo el promedio móvil con una ventana de :ventana periodos')]
    public function calculoElPromedioMovil(int $ventana): void
    {
        try {
            $this->pronostico = (new CalculadoraPronosticos())->promedioMovil($this->serie, $ventana);
        } catch (InvalidArgumentException $error) {
            $this->error = $error;
        }
    }

    #[Then('el pronóstico del siguiente periodo es :esperado unidades')]
    public function elPronosticoDelSiguientePeriodoEs(float $esperado): void
    {
        Assert::assertNull($this->error, 'Hubo un error inesperado: ' . $this->error?->getMessage());
        Assert::assertEqualsWithDelta($esperado, $this->pronostico, 0.01);
    }

    // Un texto entre comillas en la frase llega al parámetro sin las comillas.
    #[Then('se informa el error :mensaje')]
    public function seInformaElError(string $mensaje): void
    {
        Assert::assertNull($this->pronostico, 'Se esperaba un error y se obtuvo un pronóstico');
        Assert::assertSame($mensaje, $this->error?->getMessage());
    }
}
