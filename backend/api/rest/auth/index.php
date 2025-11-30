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

// ============================================
// MIDDLEWARES (se ejecutan en orden inverso)
// ============================================

// 1. JWT Middleware - Autorización de usuario
//    Excluye rutas que no requieren token JWT activo
$app->add(new JwtMiddleware(['/login', '/logout']));

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
 * POST /logout
 * 
 * Cierra la sesión actual (invalida el token).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->post('/logout', function () use ($app) {
    $authController = new AuthController($app);
    $authController->logout();
});

/**
 * POST /logout-all
 * 
 * Cierra todas las sesiones del usuario.
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->post('/logout-all', function () use ($app) {
    $authController = new AuthController($app);
    $authController->logoutAll();
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

// ============================================
// RUTAS DE GESTIÓN DE USUARIOS (SOLO ADMIN)
// ============================================

/**
 * GET /users
 * 
 * Obtiene la lista de todos los usuarios (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->get('/users', function () use ($app) {
    $authController = new AuthController($app);
    $authController->getAllUsers();
});

/**
 * PUT /users/:id/toggle-status
 * 
 * Activa o desactiva permanentemente un usuario (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->put('/users/:id/toggle-status', function ($id) use ($app) {
    $authController = new AuthController($app);
    $authController->toggleUserStatus($id);
});

/**
 * DELETE /users/:id
 * 
 * Elimina un usuario (SOLO ADMIN).
 * 
 * Headers requeridos:
 *   X-API-Key: <api_key>
 *   Authorization: Bearer <jwt_token>
 */
$app->delete('/users/:id', function ($id) use ($app) {
    $authController = new AuthController($app);
    $authController->deleteUser($id);
});

// Ejecutar la aplicación
$app->run();
