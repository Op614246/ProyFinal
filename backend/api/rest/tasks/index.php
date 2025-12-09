<?php
require_once __DIR__ . '/../../config.php';

$app->add(new JwtMiddleware([]));

$app->add(new SecurityMiddleware(getenv('API_KEY')));

$app->get('/statistics', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->getStatistics();
});

$app->get('/available', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->getAvailable();
});

$app->get('/users', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->getAvailableUsers();
});

$app->post('/', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->create();
});

$app->get('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareaById($taskId);
});

$app->post('/:id/asignar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->asignarTarea($taskId);
});

$app->post('/:id/completar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->completarTarea($taskId);
});

$app->put('/:id/iniciar', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->iniciarTarea($taskId);
});

$app->delete('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->delete($taskId);
});

$app->get('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->getAllTareas();
});

$app->get('/fecha/:fecha', function ($fecha) use ($app) {
    $controller = new TaskController($app);
    $controller->getTareasPorFecha($fecha);
});

$app->put('/:id/status', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->updateStatus($id);
});

$app->put('/:id/reabrir', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->reabrirTareaAdmin($taskId);
});


$app->run();
