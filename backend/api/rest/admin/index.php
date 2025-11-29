<?php
/**
 * API REST - Tareas Admin
 * 
 * Endpoint: /api/rest/admin/
 * 
 * Seguridad:
 * - SecurityMiddleware (API Key) → Autenticación de la app cliente
 * - JwtMiddleware (JWT Token) → Autorización del usuario
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Verificar que el archivo se está ejecutando
error_log('DEBUG: admin/index.php ejecutándose', 3, __DIR__ . '/debug.log');

// Cargar configuración base (incluye autoload con PSR-4, dotenv y crea $app)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controllers/TaskAdminController.php';

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
// RUTAS - TAREAS ADMIN
// ============================================

/**
 * GET /
 * Obtener todas las tareas admin
 */
$app->get('/', function () use ($app) {
    $controller = new TaskAdminController($app);
    $controller->getAllTareasAdmin();
});

/**
 * GET /:id
 * Obtener tarea admin específica
 */
$app->get('/:id', function ($tareaId) use ($app) {
    $controller = new TaskAdminController($app);
    $controller->getTareaAdminById($tareaId);
});

/**
 * GET /fecha/:fecha
 * Obtener tareas admin por fecha (YYYY-MM-DD)
 */
$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    $controller = new TaskAdminController($app);
    $controller->getTareasAdminPorFecha($fecha);
});

/**
 * POST /
 * Crear nueva tarea admin
 */
$app->post('/', function () use ($app) {
    $controller = new TaskAdminController($app);
    $controller->createTareaAdmin();
});

/**
 * PUT /:id
 * Actualizar tarea admin
 */
$app->put('/:id', function ($tareaId) use ($app) {
    $controller = new TaskAdminController($app);
    $controller->updateTareaAdmin($tareaId);
});

/**
 * DELETE /:id
 * Eliminar tarea admin
 */
$app->delete('/:id', function ($tareaId) use ($app) {
    $controller = new TaskAdminController($app);
    $controller->deleteTareaAdmin($tareaId);
});

// ============================================
// RUN
// ============================================
$app->run();
?>
