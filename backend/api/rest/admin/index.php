<?php
/**
 * API REST - Tareas Admin
 * 
 * Endpoint: /api/rest/admin/
 * 
 * Seguridad:
 * - SecurityMiddleware (API Key) → Autenticación de la app cliente
 * - JwtMiddleware (JWT Token) → Autorización del usuario
 * 
 * NOTA: Este archivo usa TaskController unificado (no TaskAdminController)
 */

// Error reporting - solo loguear, no mostrar en output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar configuración base (incluye autoload con PSR-4, dotenv y crea $app)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controllers/TaskController.php';
require_once __DIR__ . '/../../controllers/SubtareaController.php';
require_once __DIR__ . '/../../repository/SubtareaRepository.php';

// ============================================
// CORS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// MIDDLEWARES (se ejecutan en orden inverso)
// ============================================

// 1. JWT Middleware - Autorización de usuario
$app->add(new JwtMiddleware());

// 2. API Key Middleware - Autenticación de la aplicación
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS - TAREAS ADMIN (usando TaskController unificado)
// ============================================

/**
 * GET /
 * Obtener todas las tareas admin
 */
$app->get('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->getAllTareasAdmin();
});

/**
 * GET /fecha/:fecha
 * Obtener tareas admin por fecha (YYYY-MM-DD)
 */
$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareasAdminPorFecha($fecha);
});

/**
 * POST /
 * Crear nueva tarea admin
 */
$app->post('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->createTareaAdmin();
});

/**
 * POST /:id/asignar
 * Auto-asignar tarea al usuario autenticado
 */
$app->post('/:id/asignar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->asignarTareaAdmin($taskId);
});

/**
 * POST /:id/completar
 * Completar tarea con observaciones y evidencia
 */
$app->post('/:id/completar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->completarTareaAdmin($taskId);
});

/**
 * PUT /:id/iniciar
 * Iniciar tarea (cambiar a 'En progreso')
 */
$app->put('/:id/iniciar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->iniciarTareaAdmin($taskId);
});

/**
 * PUT /:id/reabrir
 * Reabrir tarea completada
 */
$app->put('/:id/reabrir', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->reabrirTareaAdmin($taskId);
});

/**
 * PUT /:id
 * Actualizar tarea admin
 */
$app->put('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->updateTareaAdmin($taskId);
});

/**
 * DELETE /:id
 * Eliminar tarea admin
 */
$app->delete('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->deleteTareaAdmin($taskId);
});

/**
 * GET /:id
 * Obtener tarea admin específica (AL FINAL para que no capture otras rutas)
 */
$app->get('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareaAdminById($taskId);
});

// ============================================
// RUTAS - SUBTAREAS DE UNA TAREA
// ============================================

/**
 * GET /:taskId/subtareas
 * Obtener subtareas de una tarea
 */
$app->get('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->getSubtareasByTask($taskId);
});

/**
 * POST /:taskId/subtareas
 * Crear nueva subtarea en una tarea
 */
$app->post('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->crearSubtarea($taskId);
});

/**
 * PUT /:taskId/subtareas/:id/completar
 * Completar una subtarea dentro de una tarea admin
 */
$app->put('/:taskId/subtareas/:id/completar', function ($taskId, $id) use ($app) {
    $controller = new SubtareaController($app);
    // Validación opcional de pertenencia a la tarea podría agregarse si se requiere
    $controller->completarSubtarea($id);
});

// ============================================
// RUN
// ============================================
$app->run();
?>
