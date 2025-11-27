<?php
/**
 * API REST - Autenticación
 * 
 * Endpoint: /api/rest/auth/
 * 
 * Seguridad:
 * - SecurityMiddleware (API Key) → Autenticación de la app cliente
 * - JwtMiddleware (JWT Token) → Autorización del usuario
 * 
 * Rutas públicas (solo API Key):
 * - POST /login
 * 
 * Rutas protegidas (API Key + JWT):
 * - GET /status
 * - POST /register (solo admin)
 * - POST /unlock (solo admin)
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
//    Excluye la ruta /login que no requiere token
$app->add(new JwtMiddleware(['/login']));

// 2. API Key Middleware - Autenticación de la aplicación
$app->add(new SecurityMiddleware(getenv('API_KEY')));

// ============================================
// RUTAS PÚBLICAS (solo requieren API Key)
// ============================================

/**
 * POST /login
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 * 
 * Body (encriptado AES-256):
 * {
 *   "payload": "datos_encriptados_base64",
 *   "iv": "vector_inicializacion_base64"
 * }
 * 
 * Datos desencriptados:
 * {
 *   "username": "usuario",
 *   "password": "contraseña"
 * }
 */
$app->post('/login', function () use ($app) {
    $authController = new AuthController($app);
    $authController->login();
});

// ============================================
// RUTAS PROTEGIDAS (requieren API Key + JWT)
// ============================================

/**
 * GET /status
 * 
 * Verifica el estado de la sesión actual.
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->get('/status', function () use ($app) {
    $authController = new AuthController($app);
    $authController->checkStatus();
});

/**
 * POST /register
 * 
 * Registra un nuevo usuario (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->post('/register', function () use ($app) {
    $authController = new AuthController($app);
    $authController->register();
});

/**
 * POST /unlock
 * 
 * Desbloquea una cuenta de usuario (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->post('/unlock', function () use ($app) {
    $authController = new AuthController($app);
    $authController->unlockAccount();
});

// Ejecutar la aplicación
$app->run();
