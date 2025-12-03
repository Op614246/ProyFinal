<?php
/**
 * API REST - Sucursales
 * 
 * Endpoint: /api/rest/sucursales/
 * 
 * Rutas:
 * - GET /            → Listar sucursales activas
 * - GET /:id         → Obtener sucursal por ID
 * - POST /           → Crear sucursal (Admin)
 * - PUT /:id         → Actualizar sucursal (Admin)
 * - DELETE /:id      → Eliminar sucursal (Admin)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controllers/SucursalController.php';
require_once __DIR__ . '/../../repository/SucursalRepository.php';

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Middlewares
$app->add(new JwtMiddleware([]));
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS
// ============================================

/**
 * GET /
 * Lista todas las sucursales activas
 */
$app->get('/', function () use ($app) {
    $controller = new SucursalController($app);
    $controller->getAll();
});

/**
 * GET /:id
 * Obtiene una sucursal por ID
 */
$app->get('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->getById($id);
});

/**
 * POST /
 * Crea una nueva sucursal (Solo Admin)
 */
$app->post('/', function () use ($app) {
    $controller = new SucursalController($app);
    $controller->create();
});

/**
 * PUT /:id
 * Actualiza una sucursal (Solo Admin)
 */
$app->put('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->update($id);
});

/**
 * DELETE /:id
 * Elimina una sucursal (Solo Admin)
 */
$app->delete('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->delete($id);
});

$app->run();
