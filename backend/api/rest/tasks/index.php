<?php
require_once __DIR__ . '/../../config.php';

$app->add(new JwtMiddleware([]));

$app->add(new SecurityMiddleware(getenv('API_KEY')));

$app->get('/', function () use ($app) {
    $taskController = new TaskController($app);
    $taskController->getAll();
});

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

$app->put('/:id/assign', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->assign($id);
});

$app->post('/:id/complete', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->complete($id);
});

$app->get('/:id', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->getById($id);
});

$app->put('/:id/status', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->updateStatus($id);
});

$app->delete('/:id', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->delete($id);
});

$app->put('/:id/reopen', function ($id) use ($app) {
    $taskController = new TaskController($app);
    $taskController->reopen($id);
});

$app->run();
