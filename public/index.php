<?php

declare(strict_types=1);

/**
 * Punto de entrada HTTP.
 *
 * Se levanta con el servidor incorporado de PHP (composer start), sin Apache ni Nginx.
 * Todas las peticiones llegan a este archivo, que decide qué responder según la ruta.
 */

use Pronosticos\CalculadoraPronosticos;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Verificación de vida del servicio: responde si la aplicación está arriba.
if ($metodo === 'GET' && $ruta === '/api/salud') {
    echo json_encode(['estado' => 'ok']);
    return;
}

// Calcula el MAD de dos series enviadas en el cuerpo de la petición.
if ($metodo === 'POST' && $ruta === '/api/pronosticos/mad') {
    $cuerpo = json_decode((string) file_get_contents('php://input'), true);
    $real = $cuerpo['real'] ?? null;
    $pronostico = $cuerpo['pronostico'] ?? null;

    if (!is_array($real) || !is_array($pronostico)) {
        http_response_code(400);
        echo json_encode(['error' => 'Se esperan los arreglos "real" y "pronostico"']);
        return;
    }

    try {
        $mad = (new CalculadoraPronosticos())->mad(array_values($real), array_values($pronostico));
        echo json_encode(['mad' => $mad]);
    } catch (InvalidArgumentException $error) {
        http_response_code(400);
        echo json_encode(['error' => $error->getMessage()]);
    }
    return;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada']);
