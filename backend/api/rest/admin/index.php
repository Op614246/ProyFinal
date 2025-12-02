<?php
/**
 * API REST - Tareas Admin
 * 
 * Endpoint: /api/rest/admin/
 * 
 * NOTA: Este endpoint se mantiene por compatibilidad con el frontend.
 * Las rutas redirigen al TaskController unificado.
 * 
 * Considerar migrar el frontend a usar /api/rest/tasks/ directamente.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controllers/TaskController.php';
require_once __DIR__ . '/../../controllers/SubtareaController.php';
require_once __DIR__ . '/../../repository/SubtareaRepository.php';

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Middlewares
$app->add(new JwtMiddleware());
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS - TAREAS (redirigen a TaskController)
// ============================================

/**
 * GET /
 * Obtener todas las tareas
 */
$app->get('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->getAll();
});

/**
 * GET /fecha/:fecha
 * Obtener tareas por fecha - Ahora usa filtro en getAll
 */
$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    // Inyectar el parámetro como query param para que getAll() lo use
    $_GET['fecha'] = $fecha;
    $controller = new TaskController($app);
    $controller->getAll();
});

/**
 * POST /
 * Crear nueva tarea
 */
$app->post('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->create();
});

/**
 * POST /:id/asignar
 * Asignar tarea
 */
$app->post('/:id/asignar', function ($tareaId) use ($app) {
    $controller = new TaskController($app);
    $controller->assign($tareaId);
});

/**
 * POST /:id/completar
 * Completar tarea
 */
$app->post('/:id/completar', function ($tareaId) use ($app) {
    $controller = new TaskController($app);
    $controller->complete($tareaId);
});

/**
 * PUT /:id/reabrir
 * Reabrir tarea
 */
$app->put('/:id/reabrir', function ($tareaId) use ($app) {
    $controller = new TaskController($app);
    $controller->reopen($tareaId);
});

/**
 * PUT /:id
 * Actualizar tarea - usa update en TaskController (pendiente implementar)
 */
$app->put('/:id', function ($tareaId) use ($app) {
    // TODO: Agregar método update() a TaskController si es necesario
    $controller = new TaskController($app);
    // Por ahora, updateStatus maneja cambios de estado
    $controller->updateStatus($tareaId);
});

/**
 * DELETE /:id
 * Eliminar tarea
 */
$app->delete('/:id', function ($tareaId) use ($app) {
    $controller = new TaskController($app);
    $controller->delete($tareaId);
});

/**
 * GET /:id
 * Obtener tarea específica
 */
$app->get('/:id', function ($tareaId) use ($app) {
    $controller = new TaskController($app);
    $controller->getById($tareaId);
});

// ============================================
// RUTAS - SUBTAREAS
// ============================================

/**
 * GET /:taskId/subtareas
 */
$app->get('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->getSubtareasByTask($taskId);
});

/**
 * POST /:taskId/subtareas
 */
$app->post('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->crearSubtarea($taskId);
});

$app->run();
?>
