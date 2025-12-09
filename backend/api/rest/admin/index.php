<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar configuración base (incluye autoload con PSR-4, dotenv y crea $app)
require_once __DIR__ . '/../../config.php';

$app->add(new JwtMiddleware());
$app->add(new SecurityMiddleware(getenv('API_KEY')));

$app->post('/', function () use ($app) {
    $controller = new TaskController($app);
    $controller->create();
});

$app->put('/:id', function ($taskId) use ($app) {
    $controller = new TaskController($app);
    $controller->updateStatus($taskId);
});

$app->run();
?>
