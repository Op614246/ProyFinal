<?php
require_once __DIR__ . '/../../config.php';

$app->add(new JwtMiddleware(['/login', '/logout']));

$app->add(new SecurityMiddleware(getenv('API_KEY')));


$app->post('/login', function () use ($app) {
    $authController = new AuthController($app);
    $authController->login();
});

$app->get('/status', function () use ($app) {
    $authController = new AuthController($app);
    $authController->checkStatus();
});

$app->post('/logout', function () use ($app) {
    $authController = new AuthController($app);
    $authController->logout();
});

$app->post('/logout-all', function () use ($app) {
    $authController = new AuthController($app);
    $authController->logoutAll();
});

$app->post('/register', function () use ($app) {
    $authController = new AuthController($app);
    $authController->register();
});

$app->post('/unlock', function () use ($app) {
    $authController = new AuthController($app);
    $authController->unlockAccount();
});

$app->get('/users', function () use ($app) {
    $authController = new AuthController($app);
    $authController->getAllUsers();
});

$app->put('/users/:id/toggle-status', function ($id) use ($app) {
    $authController = new AuthController($app);
    $authController->toggleUserStatus($id);
});

$app->delete('/users/:id', function ($id) use ($app) {
    $authController = new AuthController($app);
    $authController->deleteUser($id);
});

$app->run();
