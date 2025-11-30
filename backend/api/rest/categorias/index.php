<?php
/**
 * API REST - Categorías
 * 
 * Endpoint: /api/rest/categorias/
 * 
 * Rutas:
 * - GET /            → Listar categorías activas
 * - GET /:id         → Obtener categoría por ID
 * - POST /           → Crear categoría (Admin)
 * - PUT /:id         → Actualizar categoría (Admin)
 * - DELETE /:id      → Eliminar categoría (Admin)
 */

require_once __DIR__ . '/../../config.php';

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
 * Lista todas las categorías activas
 */
$app->get('/', function () use ($app) {
    $controller = new CategoriaController($app);
    $controller->getAll();
});

/**
 * GET /:id
 * Obtiene una categoría por ID
 */
$app->get('/:id', function ($id) use ($app) {
    $controller = new CategoriaController($app);
    $controller->getById($id);
});

/**
 * POST /
 * Crea una nueva categoría (Solo Admin)
 */
$app->post('/', function () use ($app) {
    $controller = new CategoriaController($app);
    $controller->create();
});

/**
 * PUT /:id
 * Actualiza una categoría (Solo Admin)
 */
$app->put('/:id', function ($id) use ($app) {
    $controller = new CategoriaController($app);
    $controller->update($id);
});

/**
 * DELETE /:id
 * Elimina una categoría (Solo Admin)
 */
$app->delete('/:id', function ($id) use ($app) {
    $controller = new CategoriaController($app);
    $controller->delete($id);
});

$app->run();
