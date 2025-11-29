<?php
/**
 * API REST - Tareas
 * 
 * Endpoint: /api/rest/tasks/
 * 
 * Seguridad:
 * - SecurityMiddleware (API Key) → Autenticación de la app cliente
 * - JwtMiddleware (JWT Token) → Autorización del usuario
 * 
 * Todas las rutas requieren API Key + JWT Token
 * 
 * Rutas:
 * - GET /            → Listar tareas (Admin: todas, User: asignadas)
 * - POST /           → Crear tarea (solo Admin)
 * - PUT /:id/assign  → Asignar tarea (User: auto-asignar, Admin: reasignar)
 * - POST /:id/complete → Completar tarea con imagen (User)
 */

// Cargar configuración base (incluye autoload con PSR-4, dotenv y crea $app)
require_once __DIR__ . '/../../config.php';

// ============================================
// CORS - Los headers se manejan en .htaccess
// Solo manejamos OPTIONS aquí por seguridad
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// MIDDLEWARES (se ejecutan en orden inverso)
// ============================================

// 1. JWT Middleware - Autorización de usuario
//    Todas las rutas de tareas requieren autenticación
$app->add(new JwtMiddleware([]));

// 2. API Key Middleware - Autenticación de la aplicación
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS PROTEGIDAS (requieren API Key + JWT)
// ============================================

/**
 * GET /
 * 
 * Lista las tareas según el rol del usuario.
 * 
 * - Admin: Puede ver todas las tareas, filtrar por fecha
 * - User: Ve solo las tareas asignadas a él, ordenadas por prioridad
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Query params (solo Admin):
 *   date_from: fecha inicio (YYYY-MM-DD)
 *   date_to: fecha fin (YYYY-MM-DD)
 *   status: filtrar por estado
 *   priority: filtrar por prioridad
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "tasks": [...]
 *   }
 * }
 */
$app->get('/', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->getAll();
});

/**
 * POST /
 * 
 * Crea una nueva tarea (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Body (encriptado AES-256):
 * {
 *   "payload": "datos_encriptados_base64",
 *   "iv": "vector_inicializacion_base64"
 * }
 * 
 * Datos desencriptados:
 * {
 *   "title": "Título de la tarea",
 *   "description": "Descripción detallada"
 * }
 * 
 * Comportamiento:
 * - Estado inicial: 'pending'
 * - Deadline: fecha actual + 2 días
 * - Prioridad por defecto: 'medium'
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Tarea creada exitosamente",
 *   "data": {
 *     "task_id": 123,
 *     "deadline": "2024-01-15"
 *   }
 * }
 */
$app->post('/', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->create();
});

/**
 * PUT /:id/assign
 * 
 * Asigna una tarea a un usuario.
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Body (encriptado AES-256):
 * {
 *   "payload": "datos_encriptados_base64",
 *   "iv": "vector_inicializacion_base64"
 * }
 * 
 * Datos desencriptados:
 * {
 *   "user_id": 5  // Opcional para Admin, ignorado para User
 * }
 * 
 * Comportamiento:
 * - User: Se auto-asigna la tarea (user_id es ignorado)
 * - Admin: Puede asignar a cualquier usuario (user_id requerido)
 * - Solo se puede asignar si la tarea está en estado 'pending'
 * - Cambia estado a 'in_process'
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Tarea asignada exitosamente",
 *   "data": {
 *     "task_id": 123,
 *     "assigned_to": 5
 *   }
 * }
 */
$app->put('/:id/assign', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->assign($id);
});

/**
 * POST /:id/complete
 * 
 * Marca una tarea como completada y sube imagen de evidencia.
 * 
 * NOTA: Se usa POST en lugar de PUT porque Slim 2.0 no maneja 
 * bien $_FILES en peticiones PUT. Esta es una limitación conocida.
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 *   Content-Type: multipart/form-data
 * 
 * Body (multipart/form-data):
 *   image: archivo de imagen (JPEG o PNG, máx 1.5MB)
 * 
 * Comportamiento:
 * - Solo el usuario asignado puede completar la tarea
 * - Solo se puede completar si está en estado 'in_process'
 * - Requiere imagen de evidencia
 * - Valida tipo (image/jpeg, image/png) y tamaño (máx 1.5MB)
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Tarea completada exitosamente",
 *   "data": {
 *     "task_id": 123,
 *     "completed_at": "2024-01-14T10:30:00Z"
 *   }
 * }
 */
$app->post('/:id/complete', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->complete($id);
});

/**
 * GET /:id
 * 
 * Obtiene el detalle de una tarea específica.
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Comportamiento:
 * - Admin: Puede ver cualquier tarea
 * - User: Solo puede ver tareas asignadas a él
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "task": { ... }
 *   }
 * }
 */
$app->get('/:id', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->getById($id);
});

/**
 * PUT /:id/status
 * 
 * Cambia el estado de una tarea (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Body (encriptado AES-256):
 * {
 *   "payload": "datos_encriptados_base64",
 *   "iv": "vector_inicializacion_base64"
 * }
 * 
 * Datos desencriptados:
 * {
 *   "status": "pending|in_process|completed|incomplete|inactive|closed"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Estado actualizado exitosamente"
 * }
 */
$app->put('/:id/status', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->updateStatus($id);
});

/**
 * DELETE /:id
 * 
 * Elimina una tarea (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Tarea eliminada exitosamente"
 * }
 */
$app->delete('/:id', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->delete($id);
});

// ============================================
// EJECUTAR APLICACIÓN
// ============================================
$app->run();
