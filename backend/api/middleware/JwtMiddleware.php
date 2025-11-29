<?php
/**
 * JwtMiddleware.php
 * 
 * Middleware para verificar tokens JWT en rutas protegidas.
 * 
 * Uso:
 * - SecurityMiddleware (API Key) → Autenticación de la aplicación
 * - JwtMiddleware (JWT Token) → Autorización del usuario
 * 
 * Header requerido: Authorization: Bearer <token>
 */

class JwtMiddleware extends \Slim\Middleware
{
    private $jwtSecret;
    private $excludedRoutes;

    /**
     * Constructor
     * 
     * @param array $excludedRoutes Rutas que no requieren JWT (ej: ['/login', '/register'])
     */
    public function __construct($excludedRoutes = [])
    {
        $this->jwtSecret = getenv('JWT_SECRET');
        $this->excludedRoutes = $excludedRoutes;
    }

    /**
     * Ejecutar el middleware
     */
    public function call()
    {
        $app = $this->app;
        // En Slim 2.0 usamos request() como método
        $request = $app->request();
        $currentPath = $request->getPathInfo();

        // Verificar si la ruta actual está excluida
        if ($this->isExcludedRoute($currentPath)) {
            $this->next->call();
            return;
        }

        // Obtener el header Authorization (Slim 2.0 usa headers() como método)
        $authHeader = $request->headers('Authorization');

        // Fallbacks si Slim no encuentra la cabecera (Apache puede pasarla en HTTP_AUTHORIZATION o REDIRECT_HTTP_AUTHORIZATION)
        if (empty($authHeader)) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } else {
                // intentar apache_request_headers si está disponible
                if (function_exists('apache_request_headers')) {
                    $headers = apache_request_headers();
                    if (!empty($headers['Authorization'])) {
                        $authHeader = $headers['Authorization'];
                    } elseif (!empty($headers['authorization'])) {
                        $authHeader = $headers['authorization'];
                    }
                }
            }
        }

        if (empty($authHeader)) {
            return $this->unauthorized($app, "Token de autorización no proporcionado.");
        }

        // Normalizar y verificar formato "Bearer <token>" (case-insensitive)
        $authHeader = trim($authHeader);
        if (stripos($authHeader, 'Bearer ') !== 0) {
            return $this->unauthorized($app, "Formato de token inválido. Use: Bearer <token>");
        }

        // Extraer el token (parte después de 'Bearer ')
        $token = trim(substr($authHeader, 7));

        if (empty($token)) {
            return $this->unauthorized($app, "Token vacío.");
        }

        // Verificar y decodificar el JWT
        $userData = $this->verifyToken($token);

        if (!$userData) {
            return $this->unauthorized($app, "Token inválido o expirado.");
        }

        // Guardar los datos del usuario en el ambiente de la aplicación
        // para que estén disponibles en los controladores
        $app->user = $userData;

        // Continuar con la siguiente capa
        $this->next->call();
    }

    /**
     * Verifica si la ruta está excluida de la validación JWT
     */
    private function isExcludedRoute($path)
    {
        foreach ($this->excludedRoutes as $route) {
            // Coincidencia exacta o con comodín
            if ($route === $path) {
                return true;
            }
            // Soporte para rutas con comodín (ej: '/public/*')
            if (substr($route, -1) === '*') {
                $prefix = substr($route, 0, -1);
                if (strpos($path, $prefix) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Verifica y decodifica un token JWT
     * 
     * @param string $token Token JWT
     * @return array|false Datos del usuario o false si es inválido
     */
    private function verifyToken($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // Verificar la firma
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Decodificar el payload
        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        if (!$payloadData) {
            return false;
        }

        // Verificar expiración
        if (!isset($payloadData['exp']) || $payloadData['exp'] < time()) {
            return false;
        }

        // Retornar los datos del usuario
        return $payloadData['data'] ?? null;
    }

    /**
     * Codifica en Base64 URL-safe
     */
    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Decodifica Base64 URL-safe
     */
    private function base64UrlDecode($data)
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }

    /**
     * Respuesta de no autorizado
     */
    private function unauthorized($app, $message)
    {
        $response = $app->response();
        $response->header('Content-Type', 'application/json');
        $response->status(401);
        $response->body(json_encode([
            "tipo" => 3,
            "mensajes" => [$message],
            "data" => null
        ], JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Middleware adicional para verificar roles específicos
 */
class RoleMiddleware extends \Slim\Middleware
{
    private $allowedRoles;

    /**
     * Constructor
     * 
     * @param array $allowedRoles Roles permitidos para acceder (ej: ['admin'])
     */
    public function __construct($allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function call()
    {
        $app = $this->app;

        // Verificar que el usuario esté autenticado (JwtMiddleware debe ejecutarse antes)
        if (!isset($app->user) || !$app->user) {
            return $this->forbidden($app, "Usuario no autenticado.");
        }

        $userRole = $app->user['role'] ?? null;

        // Si no hay roles específicos requeridos, permitir acceso
        if (empty($this->allowedRoles)) {
            $this->next->call();
            return;
        }

        // Verificar si el rol del usuario está permitido
        if (!in_array($userRole, $this->allowedRoles)) {
            return $this->forbidden($app, "No tiene permisos para acceder a este recurso. Se requiere rol: " . implode(' o ', $this->allowedRoles));
        }

        $this->next->call();
    }

    /**
     * Respuesta de acceso prohibido
     */
    private function forbidden($app, $message)
    {
        $response = $app->response();
        $response->header('Content-Type', 'application/json');
        $response->status(403);
        $response->body(json_encode([
            "tipo" => 3,
            "mensajes" => [$message],
            "data" => null
        ], JSON_UNESCAPED_UNICODE));
    }
}
