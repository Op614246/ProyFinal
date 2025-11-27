<?php
require_once __DIR__.'/../vendor/autoload.php';

// Cargar variables de entorno (phpdotenv v3.x)
$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();

// Instancia de Slim
$app = new \Slim\Slim();
$app->contentType('application/json');