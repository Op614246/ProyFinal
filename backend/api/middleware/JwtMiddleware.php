<?php
class JwtMiddleware extends \Slim\Middleware
{
    private $jwtSecret;
    private $excludedRoutes;

    public function __construct($excludedRoutes = [])
    {
        $this->jwtSecret = getenv('JWT_SECRET');
        $this->excludedRoutes = $excludedRoutes;
    }

    public function call()
    {
        $app = $this->app;
        $request = $app->request();
        $currentPath = $request->getPathInfo();

        if ($this->isExcludedRoute($currentPath)) {
            $this->next->call();
            return;
        }

        $authHeader = $request->headers('Authorization');

        if (empty($authHeader)) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } else {
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

        $authHeader = trim($authHeader);
        if (stripos($authHeader, 'Bearer ') !== 0) {
            return $this->unauthorized($app, "Formato de token inválido. Use: Bearer <token>");
        }

        $token = trim(substr($authHeader, 7));

        if (empty($token)) {
            return $this->unauthorized($app, "Token vacío.");
        }

        $userData = $this->verifyToken($token);

        if (!$userData) {
            return $this->unauthorized($app, "Token inválido o expirado.");
        }

        $app->user = $userData;

        $this->next->call();
    }

    private function isExcludedRoute($path)
    {
        foreach ($this->excludedRoutes as $route) {
            if ($route === $path) {
                return true;
            }
            if (substr($route, -1) === '*') {
                $prefix = substr($route, 0, -1);
                if (strpos($path, $prefix) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function verifyToken($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        if (!$payloadData) {
            return false;
        }

        if (!isset($payloadData['exp']) || $payloadData['exp'] < time()) {
            return false;
        }

        return $payloadData['data'] ?? null;
    }

    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64UrlDecode($data)
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }

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

class RoleMiddleware extends \Slim\Middleware
{
    private $allowedRoles;

    public function __construct($allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function call()
    {
        $app = $this->app;

        if (!isset($app->user) || !$app->user) {
            return $this->forbidden($app, "Usuario no autenticado.");
        }

        $userRole = $app->user['role'] ?? null;

        if (empty($this->allowedRoles)) {
            $this->next->call();
            return;
        }

        if (!in_array($userRole, $this->allowedRoles)) {
            return $this->forbidden($app, "No tiene permisos para acceder a este recurso. Se requiere rol: " . implode(' o ', $this->allowedRoles));
        }

        $this->next->call();
    }

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
