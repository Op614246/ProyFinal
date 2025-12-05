<?php
require_once __DIR__ . '/../../config.php';

$app->add(new JwtMiddleware([]));
$app->add(new SecurityMiddleware(getenv('API_KEY')));

$app->get('/', function () use ($app) {
    $controller = new SucursalController($app);
    $controller->getAll();
});

$app->get('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->getById($id);
});

$app->post('/', function () use ($app) {
    $controller = new SucursalController($app);
    $controller->create();
});

$app->put('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->update($id);
});

$app->delete('/:id', function ($id) use ($app) {
    $controller = new SucursalController($app);
    $controller->delete($id);
});

$app->run();
