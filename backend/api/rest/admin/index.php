<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar configuración base (incluye autoload con PSR-4, dotenv y crea $app)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controllers/TaskController.php';
require_once __DIR__ . '/../../controllers/SubtareaController.php';
require_once __DIR__ . '/../../repository/SubtareaRepository.php';

$app->add(new JwtMiddleware());
$app->add(new SecurityMiddleware(getenv('API_KEY')));

$app->get('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->getAllTareasAdmin();
});

$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareasAdminPorFecha($fecha);
});

$app->post('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->createTareaAdmin();
});

$app->post('/:id/asignar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->asignarTareaAdmin($taskId);
});

$app->post('/:id/completar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->completarTareaAdmin($taskId);
});

$app->put('/:id/iniciar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->iniciarTareaAdmin($taskId);
});

$app->put('/:id/reabrir', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->reabrirTareaAdmin($taskId);
});

$app->put('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->updateTareaAdmin($taskId);
});

$app->delete('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->deleteTareaAdmin($taskId);
});

$app->get('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareaAdminById($taskId);
});

$app->get('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->getSubtareasByTask($taskId);
});

$app->post('/:taskId/subtareas', function ($taskId) use ($app) {
    $controller = new SubtareaController($app);
    $controller->crearSubtarea($taskId);
});

$app->put('/:taskId/subtareas/:id/completar', function ($taskId, $id) use ($app) {
    $controller = new SubtareaController($app);
    // Validación opcional de pertenencia a la tarea podría agregarse si se requiere
    $controller->completarSubtarea($id);
});

$app->run();
?>
