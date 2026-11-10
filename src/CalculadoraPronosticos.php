<?php

declare(strict_types=1);

namespace Pronosticos;

use InvalidArgumentException;

/**
 * Pronósticos de demanda y medidas de error.
 *
 * Reúne las reglas de negocio del sistema de predicción de demanda: cómo se calcula
 * un pronóstico y cómo se mide qué tan lejos quedó de la demanda real. No depende de
 * bases de datos ni de servicios externos, por eso se puede probar con pruebas
 * unitarias rápidas y aisladas.
 */
final class CalculadoraPronosticos
{
    /**
     * Pronostica el siguiente periodo con el promedio de los últimos $ventana valores.
     * Ejemplo: serie [120, 132, 128, 141, 138, 150] y ventana 3 → (141 + 138 + 150) / 3 = 143.
     *
     * @param list<int|float> $serie
     */
    public function promedioMovil(array $serie, int $ventana): float
    {
        if ($ventana <= 0) {
            throw new InvalidArgumentException('La ventana debe ser un entero positivo');
        }
        if (count($serie) < $ventana) {
            throw new InvalidArgumentException('La serie tiene menos periodos que la ventana configurada');
        }

        $ultimos = array_slice($serie, -$ventana);

        return array_sum($ultimos) / $ventana;
    }

    /**
     * Error absoluto medio (MAD): promedio de las diferencias absolutas, en unidades.
     *
     * @param list<int|float> $real
     * @param list<int|float> $pronostico
     */
    public function mad(array $real, array $pronostico): float
    {
        $this->validarSeries($real, $pronostico);

        $erroresAbsolutos = array_map(
            static fn (int|float $valorReal, int|float $valorPronosticado): float => abs($valorReal - $valorPronosticado),
            $real,
            $pronostico,
        );

        return array_sum($erroresAbsolutos) / count($erroresAbsolutos);
    }

    /**
     * Error porcentual absoluto medio (MAPE), expresado en porcentaje.
     *
     * Un periodo con demanda real igual a 0 no se puede expresar en porcentaje
     * (dividiría entre cero), así que se excluye del cálculo. Si todos los periodos
     * tienen demanda 0, el MAPE no está definido y se lanza una excepción.
     *
     * @param list<int|float> $real
     * @param list<int|float> $pronostico
     */
    public function mape(array $real, array $pronostico): float
    {
        $this->validarSeries($real, $pronostico);

        $erroresPorcentuales = [];
        foreach ($real as $i => $valorReal) {
            if ((float) $valorReal === 0.0) {
                continue;
            }
            $erroresPorcentuales[] = abs($valorReal - $pronostico[$i]) / $valorReal * 100;
        }

        if ($erroresPorcentuales === []) {
            throw new InvalidArgumentException('El MAPE no está definido: todos los periodos tienen demanda 0');
        }

        return array_sum($erroresPorcentuales) / count($erroresPorcentuales);
    }

    /**
     * Indica si un error de pronóstico debe disparar una alerta.
     *
     * Regla acordada con Producción: la alerta se activa cuando el error es mayor
     * o igual al umbral. Un error exactamente igual al umbral sí alerta.
     */
    public function debeGenerarAlerta(float $errorPorcentual, float $umbral): bool
    {
        if ($umbral < 0 || $umbral > 100) {
            throw new InvalidArgumentException('El umbral debe estar entre 0 y 100');
        }

        return $errorPorcentual >= $umbral;
    }

    /**
     * @param list<int|float> $real
     * @param list<int|float> $pronostico
     */
    private function validarSeries(array $real, array $pronostico): void
    {
        if (count($real) !== count($pronostico)) {
            throw new InvalidArgumentException('Las series deben tener el mismo número de periodos');
        }
        if ($real === []) {
            throw new InvalidArgumentException('Se necesita al menos un periodo');
        }
    }
}
