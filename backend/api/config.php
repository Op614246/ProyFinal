<?php
// =============================================================================
// CONFIGURACIÓN GLOBAL DE LA API
// =============================================================================

// Zona horaria
date_default_timezone_set('America/Lima');

// =============================================================================
// CONSTANTES DE EVIDENCIAS/ARCHIVOS
// =============================================================================
define('MAX_FILE_SIZE_KB', 1536);          // 1.5 MB en KB
define('MAX_FILE_SIZE_BYTES', 1536 * 1024); // 1.5 MB en bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/evidencias/');
define('UPLOAD_PATH_RELATIVE', 'uploads/evidencias/');

// =============================================================================
// CONSTANTES DE ESTADOS Y PRIORIDADES
// =============================================================================
define('STATUS_PENDING', 'pending');
define('STATUS_IN_PROCESS', 'in_process');
define('STATUS_COMPLETED', 'completed');
define('STATUS_INCOMPLETE', 'incomplete');
define('STATUS_INACTIVE', 'inactive');

define('PRIORITY_HIGH', 'high');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_LOW', 'low');

// Mapeo de estados internos a legacy (español)
define('STATUS_MAP', [
    'pending' => 'Pendiente',
    'in_process' => 'En progreso',
    'completed' => 'Completada',
    'incomplete' => 'Incompleta',
    'inactive' => 'Inactiva'
]);

// Mapeo de prioridades internas a legacy (español)
define('PRIORITY_MAP', [
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baja'
]);

// =============================================================================
// CONFIGURACIÓN DE ERRORES
// =============================================================================

// Desactivar errores en output para no romper JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Cargar clases core
require_once __DIR__ . '/core/DB.php';

// Instancia de Slim
$app = new \Slim\Slim();
$app->contentType('application/json; charset=utf-8');