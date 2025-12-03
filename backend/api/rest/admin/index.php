<?php
/**
 * API REST - Admin (Legacy)
 * 
 * Endpoint: /api/rest/admin/
 * 
 * NOTA: Este endpoint está DEPRECADO.
 * 
 * Las operaciones se han movido a:
 * - Tareas → /api/rest/tasks/ (con diferenciación por rol)
 * - Subtareas → /api/rest/subtareas/
 * 
 * Se mantienen solo rutas legacy para compatibilidad temporal.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config.php';

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Middlewares
$app->add(new JwtMiddleware());
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS LEGACY (Redirigen a /tasks/)
// Se mantienen por compatibilidad temporal
// TODO: Actualizar frontend para usar /tasks/ directamente
// ============================================

/**
 * @deprecated Usar GET /api/rest/tasks/
 */
$app->get('/', function () use ($app) {
    require_once __DIR__ . '/../../controllers/TaskController.php';
    $controller = new TaskController($app);
    $controller->getAll();
});

/**
 * @deprecated Usar GET /api/rest/tasks/?fecha=YYYY-MM-DD
 */
$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    $_GET['fecha'] = $fecha;
    require_once __DIR__ . '/../../controllers/TaskController.php';
    $controller = new TaskController($app);
    $controller->getAll();
});

$app->run();
