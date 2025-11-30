<?php
// Establecer charset UTF-8
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// Manejar CORS dinámico cuando el navegador envía Origin
// (si el frontend envía credenciales, el header debe ser el origen explícito,
// por eso preferimos devolver el Origin recibido en lugar de '*')
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
if ($origin) {
    // Reemplazar cualquier header CORS anterior para devolver el origen exacto
    header("Access-Control-Allow-Origin: $origin", true);
    header('Access-Control-Allow-Credentials: true', true);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-KEY, Accept', true);
    header('Access-Control-Max-Age: 3600', true);
}

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__.'/../vendor/autoload.php';

// Cargar variables de entorno (phpdotenv v3.x)
$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();

// Instancia de Slim
$app = new \Slim\Slim();
$app->contentType('application/json; charset=utf-8');