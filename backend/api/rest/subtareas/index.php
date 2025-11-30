<?php
/**
 * API REST - Subtareas
 * 
 * Endpoint: /api/rest/subtareas/
 * 
 * Rutas:
 * - GET /mis-subtareas     → Obtener subtareas asignadas al usuario
 * - GET /:id               → Obtener subtarea por ID
 * - PUT /:id               → Actualizar subtarea
 * - DELETE /:id            → Eliminar subtarea
 * - PUT /:id/completar     → Completar subtarea
 * - PUT /:id/iniciar       → Iniciar subtarea
 * - PUT /:id/asignar       → Asignar subtarea a usuario
 */

require_once __DIR__ . '/../../config.php';
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

$controller = new SubtareaController($app);

// ===============================
// RUTAS ESPECÍFICAS (antes de :id)
// ===============================

// Obtener subtareas del usuario actual
$app->get('/mis-subtareas', function() use ($controller) {
    $controller->getMisSubtareas();
});

// Obtener subtareas de una tarea específica
$app->get('/task/:taskId', function($taskId) use ($controller) {
    $controller->getSubtareasByTask($taskId);
});

// Crear nueva subtarea
$app->post('/', function() use ($controller) {
    $controller->crearSubtareaGeneral();
});

// ===============================
// RUTAS CON PARÁMETROS
// ===============================

// Obtener una subtarea por ID
$app->get('/:id', function($id) use ($controller) {
    $controller->getSubtarea($id);
});

// Actualizar subtarea
$app->put('/:id', function($id) use ($controller) {
    $controller->actualizarSubtarea($id);
});

// Eliminar subtarea
$app->delete('/:id', function($id) use ($controller) {
    $controller->eliminarSubtarea($id);
});

// Completar subtarea
$app->put('/:id/completar', function($id) use ($controller) {
    $controller->completarSubtarea($id);
});

// Iniciar subtarea
$app->put('/:id/iniciar', function($id) use ($controller) {
    $controller->iniciarSubtarea($id);
});

// Asignar subtarea a usuario
$app->put('/:id/asignar', function($id) use ($controller) {
    $controller->asignarSubtarea($id);
});

// ============================================
// RUN
// ============================================
$app->run();
