<?php

declare(strict_types=1);

/**
 * Verifica que la cobertura de líneas alcance un mínimo.
 *
 * PHPUnit genera el reporte de cobertura pero no tiene una opción para fallar
 * si el porcentaje es bajo. Este script lee el reporte en formato Clover que
 * produce `composer test:cobertura` y termina con error si no se alcanza el mínimo.
 * Así el pipeline puede detenerse cuando la cobertura baja.
 *
 * Uso: php bin/verificar-cobertura.php coverage.xml 90
 */

$archivo = $argv[1] ?? 'coverage.xml';
$minimo = (float) ($argv[2] ?? 90);

if (!is_file($archivo)) {
    fwrite(STDERR, "No se encontró el reporte {$archivo}. Ejecuten primero: composer test:cobertura\n");
    exit(1);
}

$reporte = simplexml_load_file($archivo);
if ($reporte === false) {
    fwrite(STDERR, "El archivo {$archivo} no es un reporte Clover válido.\n");
    exit(1);
}

$metricas = $reporte->project->metrics;
$sentencias = (int) $metricas['statements'];
$cubiertas = (int) $metricas['coveredstatements'];
$porcentaje = $sentencias === 0 ? 100.0 : $cubiertas / $sentencias * 100;

printf("Cobertura de líneas: %.2f %% (mínimo exigido: %.0f %%)\n", $porcentaje, $minimo);

if ($porcentaje < $minimo) {
    fwrite(STDERR, "La cobertura está por debajo del mínimo.\n");
    exit(1);
}
