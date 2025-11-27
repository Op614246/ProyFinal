<?php
/**
 * AuthController.php
 * 
 * Controlador de autenticación con:
 * - Encriptación AES-256 de datos en tránsito
 * - Sistema de bloqueo escalonado (5min -> 10min -> permanente)
 * - Mensajes personalizados por rol
 * - Logging con Monolog
 */

require_once __DIR__ . '/../core/Logger.php';

class AuthController {

    private $app;
    private $repository;
    private $encryptionKey;
    private $jwtSecret;

    // Constantes de configuración de bloqueo
    const LOCKOUT_WINDOW = 120;        // 2 minutos en segundos (ventana para acumular intentos)
    const FIRST_LOCKOUT_MINUTES = 1;   // Primer bloqueo: 5 minutos
    const SECOND_LOCKOUT_MINUTES = 2; // Segundo bloqueo: 10 minutos
    const ATTEMPTS_PER_LEVEL = 3;      // Intentos antes de cada nivel de bloqueo

    public function __construct($app) {
        $this->app = $app;
        $this->repository = new AuthRepository();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
        $this->jwtSecret = getenv('JWT_SECRET');
    }

    /**
     * Proceso de login con datos encriptados
     */
    public function login() {
        try {
            // 1. Obtener y desencriptar los datos de la petición
            $requestBody = $this->app->request()->getBody();
            $encryptedData = json_decode($requestBody, true);

            if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
                Logger::warning('Intento de login con formato inválido', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->response(3, ["Formato de petición inválido. Se requiere payload encriptado."]);
            }

            // Desencriptar los datos
            $decryptedData = $this->decryptData($encryptedData['payload'], $encryptedData['iv']);
            
            if (!$decryptedData) {
                Logger::error('Error al desencriptar datos de login', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->response(3, ["Error al procesar los datos de autenticación."]);
            }

            $credentials = json_decode($decryptedData, true);
            
            if (!$credentials || !isset($credentials['username']) || !isset($credentials['password'])) {
                return $this->response(3, ["Credenciales incompletas."]);
            }

            $username = trim($credentials['username']);
            $password = $credentials['password'];

            // 2. Validar que no estén vacíos
            if (empty($username) || empty($password)) {
                return $this->response(3, ["Usuario y contraseña son requeridos."]);
            }

            // 3. Buscar usuario en la base de datos
            $user = $this->repository->obtenerUsuarioPorUsername($username);

            if (!$user) {
                // Usuario no existe - no revelar esta información específica
                return $this->response(3, ["Credenciales incorrectas."]);
            }

            // 4. Verificar si la cuenta está bloqueada permanentemente
            if ($user['is_permanently_locked']) {
                return $this->response(3, [
                    "Su cuenta ha sido bloqueada permanentemente debido a múltiples intentos fallidos.",
                    "Contacte al administrador del sistema para desbloquearla."
                ]);
            }

            // 5. Verificar si hay un bloqueo temporal activo
            if ($user['lockout_until']) {
                // Usar MySQL para comparar fechas y evitar problemas de zona horaria
                $db = DB::getInstance()->dbh;
                $stmt = $db->prepare("SELECT 
                    TIMESTAMPDIFF(SECOND, NOW(), :lockout_until) as seconds_remaining,
                    NOW() as now_time");
                $stmt->execute([':lockout_until' => $user['lockout_until']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $secondsRemaining = (int)$result['seconds_remaining'];
                
                if ($secondsRemaining > 0) {
                    $minutesLeft = floor($secondsRemaining / 60);
                    $secondsLeft = $secondsRemaining % 60;

                    Logger::warning('Intento de login en cuenta bloqueada', [
                        'username' => $username,
                        'seconds_remaining' => $secondsRemaining,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    return $this->response(3, [
                        "Su cuenta está temporalmente bloqueada.",
                        "Tiempo restante: {$minutesLeft} minutos y {$secondsLeft} segundos.",
                        "Intente nuevamente después de ese tiempo."
                    ]);
                }
            }

            // 6. Verificar la contraseña
            if (password_verify($password, $user['password_hash'])) {
                // Login exitoso - Limpiar intentos fallidos
                $this->repository->limpiarIntentos($user['id']);

                // Usar el username desencriptado
                $usernameDisplay = $user['username_plain'] ?? $username;

                // Generar JWT con username desencriptado
                $jwtToken = $this->generateJWTWithUsername($user, $usernameDisplay);

                // Registrar la sesión en la base de datos
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora
                $sessionId = $this->repository->crearSesion($user['id'], $jwtToken, $expiresAt);

                // Log de login exitoso
                Logger::loginAttempt($usernameDisplay, true, null, [
                    'role' => $user['role'],
                    'user_id' => $user['id'],
                    'session_id' => $sessionId
                ]);

                // Mensaje personalizado según el rol
                $welcomeMessage = $this->getWelcomeMessage($user['role'], $usernameDisplay);

                // Encriptar la respuesta
                $responseData = [
                    'token' => $jwtToken,
                    'role' => $user['role'],
                    'username' => $usernameDisplay
                ];

                $encryptedResponse = $this->encryptData(json_encode($responseData));

                return $this->response(1, [$welcomeMessage], [
                    'encrypted' => true,
                    'payload' => $encryptedResponse['payload'],
                    'iv' => $encryptedResponse['iv']
                ]);

            } else {
                // Contraseña incorrecta - procesar el fallo
                return $this->procesarFallo($user);
            }

        } catch (Exception $e) {
            // Log del error
            Logger::error('Error en proceso de login', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->response(3, ["Error interno del servidor."]);
        }
    }

    /**
     * Registrar nuevo usuario (solo para admins)
     * El JwtMiddleware ya validó el token, usamos $app->user
     */
    public function register() {
        try {
            // Obtener usuario del middleware JWT
            $userData = $this->getAuthenticatedUser();
            
            // Verificar rol admin
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->response(3, ["No tiene permisos para realizar esta acción. Se requiere rol de administrador."]);
            }

            // Obtener datos encriptados
            $requestBody = $this->app->request()->getBody();
            $encryptedData = json_decode($requestBody, true);

            if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
                return $this->response(3, ["Formato de petición inválido."]);
            }

            $decryptedData = $this->decryptData($encryptedData['payload'], $encryptedData['iv']);
            $data = json_decode($decryptedData, true);

            if (!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
                return $this->response(3, ["Datos incompletos para registro."]);
            }

            // Validar rol
            if (!in_array($data['role'], ['admin', 'user'])) {
                return $this->response(3, ["Rol inválido."]);
            }

            // Hash de la contraseña
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

            // Crear usuario
            $result = $this->repository->crearUsuario($data['username'], $passwordHash, $data['role']);

            if ($result) {
                Logger::info('Usuario creado exitosamente', [
                    'new_username' => $data['username'],
                    'role' => $data['role'],
                    'created_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->response(1, ["Usuario '{$data['username']}' creado exitosamente con rol '{$data['role']}'."]);
            } else {
                Logger::warning('Error al crear usuario', [
                    'username' => $data['username'],
                    'created_by' => $userData['username']
                ]);
                return $this->response(3, ["Error al crear el usuario. El nombre de usuario podría estar en uso."]);
            }

        } catch (Exception $e) {
            Logger::error('Error en registro de usuario', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->response(3, ["Error interno del servidor."]);
        }
    }

    /**
     * Desbloquear cuenta (solo para admins)
     * El JwtMiddleware ya validó el token, usamos $app->user
     */
    public function unlockAccount() {
        try {
            // Obtener usuario del middleware JWT
            $userData = $this->getAuthenticatedUser();
            
            // Verificar rol admin
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->response(3, ["No tiene permisos para realizar esta acción. Se requiere rol de administrador."]);
            }

            $requestBody = $this->app->request()->getBody();
            $encryptedData = json_decode($requestBody, true);

            if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
                return $this->response(3, ["Formato de petición inválido."]);
            }

            $decryptedData = $this->decryptData($encryptedData['payload'], $encryptedData['iv']);
            $data = json_decode($decryptedData, true);

            if (!$data || !isset($data['username'])) {
                return $this->response(3, ["Se requiere el nombre de usuario a desbloquear."]);
            }

            $result = $this->repository->desbloquearCuenta($data['username']);

            if ($result) {
                Logger::accountUnlocked($data['username'], $userData['username']);
                return $this->response(1, ["Cuenta '{$data['username']}' desbloqueada exitosamente."]);
            } else {
                Logger::warning('Intento de desbloquear usuario inexistente', [
                    'username' => $data['username'],
                    'unlocked_by' => $userData['username']
                ]);
                return $this->response(3, ["Usuario no encontrado."]);
            }

        } catch (Exception $e) {
            Logger::error('Error al desbloquear cuenta', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->response(3, ["Error interno del servidor."]);
        }
    }

    /**
     * Verificar estado de sesión
     * El JwtMiddleware ya validó el token, usamos $app->user
     */
    public function checkStatus() {
        try {
            $userData = $this->getAuthenticatedUser();
            if (!$userData) {
                return $this->response(3, ["Sesión no válida o expirada."]);
            }

            return $this->response(1, ["Sesión activa."], [
                'id' => $userData['id'],
                'username' => $userData['username'],
                'role' => $userData['role']
            ]);

        } catch (Exception $e) {
            return $this->response(3, ["Error al verificar sesión."]);
        }
    }

    /**
     * Cerrar sesión (logout)
     * Invalida el token actual en la base de datos
     */
    public function logout() {
        try {
            $userData = $this->getAuthenticatedUser();
            
            // Obtener el token del header
            $authHeader = $this->app->request()->headers('Authorization');
            if (!$authHeader) {
                $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
            }
            
            if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                
                // Invalidar la sesión en la base de datos
                $invalidated = $this->repository->invalidarSesion($token);
                
                if ($invalidated) {
                    Logger::info('Logout exitoso', [
                        'username' => $userData ? $userData['username'] : 'unknown',
                        'user_id' => $userData ? $userData['id'] : 'unknown',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    
                    return $this->response(1, ["Sesión cerrada correctamente."]);
                }
            }
            
            // Aunque no se encontró la sesión, respondemos exitosamente
            // (el usuario ya no tiene acceso de todos modos)
            return $this->response(1, ["Sesión cerrada."]);

        } catch (Exception $e) {
            Logger::error('Error en logout', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->response(3, ["Error al cerrar sesión."]);
        }
    }

    /**
     * Cerrar todas las sesiones del usuario (logout de todos los dispositivos)
     */
    public function logoutAll() {
        try {
            $userData = $this->getAuthenticatedUser();
            if (!$userData) {
                return $this->response(3, ["Sesión no válida."]);
            }

            $count = $this->repository->invalidarTodasLasSesiones($userData['id']);
            
            Logger::info('Logout de todas las sesiones', [
                'username' => $userData['username'],
                'user_id' => $userData['id'],
                'sessions_closed' => $count,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $this->response(1, ["Se cerraron {$count} sesión(es) activa(s)."]);

        } catch (Exception $e) {
            Logger::error('Error en logout all', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->response(3, ["Error al cerrar sesiones."]);
        }
    }

    /**
     * Obtiene el usuario autenticado desde el middleware JWT
     * 
     * @return array|null Datos del usuario o null si no está autenticado
     */
    private function getAuthenticatedUser() {
        // El JwtMiddleware guarda los datos del usuario en $app->user
        return isset($this->app->user) ? $this->app->user : null;
    }

    /**
     * Procesa los intentos fallidos con el sistema de bloqueo escalonado
     * 
     * Lógica:
     * - 3 intentos en 2 min → bloqueo 5 min (nivel 1)
     * - 3 intentos más después del desbloqueo → bloqueo 10 min (nivel 2)
     * - 3 intentos más después del desbloqueo → bloqueo permanente (nivel 3)
     */
    private function procesarFallo($user) {
        $currentFailures = (int)$user['failed_attempts'];
        
        // Obtener la hora actual y calcular diferencia directamente en MySQL
        $db = DB::getInstance()->dbh;
        $nowResult = $db->query("SELECT NOW() as now")->fetch(PDO::FETCH_ASSOC);
        $now = new DateTime($nowResult['now']);
        
        // Calcular segundos desde el último intento usando MySQL
        $secondsSinceLastAttempt = 0;
        if ($user['last_attempt_time']) {
            $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, :last_attempt, NOW()) as diff");
            $stmt->execute([':last_attempt' => $user['last_attempt_time']]);
            $diffResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $secondsSinceLastAttempt = (int)$diffResult['diff'];
        }

        $newFailures = 0;
        $lockoutTime = null;
        $permanentLock = 0;
        $mensajes = ["❌ Credenciales incorrectas."];

        // Determinar si reiniciamos el contador (si pasaron más de 2 minutos desde el último intento)
        $resetCounter = false;
        if ($user['last_attempt_time']) {
            // Solo reiniciamos si no hay bloqueo activo y pasaron más de 2 minutos
            if ($secondsSinceLastAttempt > self::LOCKOUT_WINDOW && !$user['lockout_until']) {
                $resetCounter = true;
            }
        }

        if ($resetCounter) {
            $newFailures = 1;
        } else {
            $newFailures = $currentFailures + 1;
        }

        // Calcular intentos restantes para el siguiente nivel de bloqueo
        $attemptsInCurrentLevel = ($newFailures - 1) % self::ATTEMPTS_PER_LEVEL + 1;
        $attemptsRemaining = self::ATTEMPTS_PER_LEVEL - $attemptsInCurrentLevel;

        // Determinar el nivel de bloqueo actual
        $blockLevel = ceil($newFailures / self::ATTEMPTS_PER_LEVEL);

        // Log de intento fallido
        Logger::loginAttempt($user['username_plain'] ?? 'unknown', false, null, [
            'user_id' => $user['id'],
            'attempt_number' => $newFailures,
            'block_level' => $blockLevel
        ]);

        // Aplicar bloqueo según el nivel alcanzado
        if ($newFailures % self::ATTEMPTS_PER_LEVEL === 0) {
            // Se alcanzó el límite de intentos para este nivel
            switch ($blockLevel) {
                case 1:
                    // Primer bloqueo: 5 minutos
                    $lockoutTime = (clone $now)->modify('+' . self::FIRST_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    $mensajes[] = "Ha superado los 3 intentos permitidos.";
                    $mensajes[] = "Su cuenta ha sido bloqueada por 5 minutos.";
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'temporal_5min', '5 minutos');
                    break;
                
                case 2:
                    // Segundo bloqueo: 10 minutos
                    $lockoutTime = (clone $now)->modify('+' . self::SECOND_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    $mensajes[] = "Reincidencia en intentos fallidos detectada.";
                    $mensajes[] = "Su cuenta ha sido bloqueada por 10 minutos.";
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'temporal_10min', '10 minutos');
                    break;
                
                case 3:
                default:
                    // Tercer bloqueo: Permanente
                    $permanentLock = 1;
                    $mensajes = []; // Limpiar mensajes anteriores
                    $mensajes[] = "Su cuenta ha sido bloqueada permanentemente.";
                    $mensajes[] = "Debe contactar al administrador del sistema para recuperar el acceso.";
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'permanente', null);
                    break;
            }
        } else {
            // Advertencia de intentos restantes
            if ($attemptsRemaining > 0) {
                $mensajes[] = "Le quedan {$attemptsRemaining} intento(s) antes del bloqueo.";
            }
        }

        // Guardar el estado actualizado en la base de datos
        $this->repository->actualizarIntentoFallido(
            $user['id'],
            $newFailures,
            $lockoutTime,
            $permanentLock
        );

        return $this->response(3, $mensajes);
    }

    /**
     * Genera mensaje de bienvenida según el rol
     */
    private function getWelcomeMessage($role, $username) {
        switch ($role) {
            case 'admin':
                return "¡Bienvenido Administrador {$username}! Tienes acceso completo al sistema.";
            case 'user':
                return "¡Hola {$username}! Has iniciado sesión como usuario.";
            default:
                return "¡Bienvenido {$username}!";
        }
    }

    /**
     * Genera un token JWT
     */
    private function generateJWT($user) {
        return $this->generateJWTWithUsername($user, $user['username_plain'] ?? $user['username']);
    }

    /**
     * Genera un token JWT con username específico
     */
    private function generateJWTWithUsername($user, $username) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            "iat" => time(),
            "exp" => time() + (60 * 60), // 1 hora de expiración
            "data" => [
                "id" => $user['id'],
                "username" => $username,
                "role" => $user['role']
            ]
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtSecret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Verifica un token JWT y retorna los datos del usuario
     */
    private function verifyJWT() {
        $authHeader = $this->app->request()->headers('Authorization');
        
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return null;
        }

        $token = substr($authHeader, 7);
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        list($header, $payload, $signature) = $parts;

        // Verificar firma
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true)
        );

        if ($signature !== $expectedSignature) {
            return null;
        }

        // Decodificar payload
        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        // Verificar expiración
        if (!$payloadData || !isset($payloadData['exp']) || $payloadData['exp'] < time()) {
            return null;
        }

        return $payloadData['data'];
    }

    /**
     * Encripta datos usando AES-256-CBC
     */
    private function encryptData($data) {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        return [
            'payload' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Desencripta datos usando AES-256-CBC
     */
    private function decryptData($encryptedPayload, $iv) {
        try {
            $key = hash('sha256', $this->encryptionKey, true);
            $iv = base64_decode($iv);
            $encrypted = base64_decode($encryptedPayload);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Codifica en Base64 URL-safe
     */
    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Decodifica Base64 URL-safe
     */
    private function base64UrlDecode($data) {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }

    /**
     * Envía la respuesta JSON
     */
    private function response($tipo, $mensajes, $data = null) {
        // Escribimos la respuesta en el objeto Response de Slim
        $response = $this->app->response();
        $response->header('Content-Type', 'application/json');
        $response->body(json_encode([
            "tipo" => $tipo,
            "mensajes" => $mensajes,
            "data" => $data
        ], JSON_UNESCAPED_UNICODE));
        // Simplemente retornamos; la ruta/closure terminará y Slim enviará la respuesta.
        return;
    }
}