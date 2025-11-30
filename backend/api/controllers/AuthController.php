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
require_once __DIR__ . '/../../smart/validators/AuthValidator.php';

class AuthController {

    private $app;
    private $repository;
    private $validator;
    private $encryptionKey;
    private $jwtSecret;

    // Constantes de configuración de bloqueo
    const LOCKOUT_WINDOW = 120;        // 2 minutos en segundos (ventana para acumular intentos)
    const FIRST_LOCKOUT_MINUTES = 5;   // Primer bloqueo: 5 minutos
    const SECOND_LOCKOUT_MINUTES = 10; // Segundo bloqueo: 10 minutos
    const ATTEMPTS_PER_LEVEL = 3;      // Intentos antes de cada nivel de bloqueo

    public function __construct($app) {
        $this->app = $app;
        $this->repository = new AuthRepository();
        $this->validator = new AuthValidator();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
        $this->jwtSecret = getenv('JWT_SECRET');
    }

    /**
     * Proceso de login con datos encriptados
     */
    public function login() {
        try {
            // 1. Obtener y validar datos encriptados
            $encryptedData = $this->getEncryptedRequest();
            
            if (!$encryptedData) {
                Logger::warning('Intento de login con formato inválido', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->invalidRequestFormat());
            }

            // 2. Desencriptar los datos
            $credentials = $this->decryptCredentials($encryptedData);
            
            if (!$credentials) {
                Logger::error('Error al desencriptar datos de login', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->decryptionError());
            }

            // 3. Validar credenciales
            if (!isset($credentials['username']) || !isset($credentials['password'])) {
                return $this->sendResponse($this->validator->incompleteCredentials());
            }

            $username = trim($credentials['username']);
            $password = $credentials['password'];

            if (empty($username) || empty($password)) {
                return $this->sendResponse($this->validator->emptyFields());
            }

            // 4. Buscar usuario
            $user = $this->repository->obtenerUsuarioPorUsername($username);

            if (!$user) {
                return $this->sendResponse($this->validator->invalidCredentials());
            }

            // 5. Verificar bloqueo permanente
            if ($user['is_permanently_locked']) {
                return $this->sendResponse($this->validator->permanentlyLocked());
            }

            // 6. Verificar bloqueo temporal
            $lockCheck = $this->checkTemporaryLock($user, $username);
            if ($lockCheck) {
                return $this->sendResponse($lockCheck);
            }

            // 7. Verificar contraseña
            if (password_verify($password, $user['password_hash'])) {
                return $this->handleSuccessfulLogin($user, $username);
            } else {
                return $this->procesarFallo($user);
            }

        } catch (Exception $e) {
            Logger::error('Error en proceso de login', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * Registrar nuevo usuario (solo para admins)
     */
    public function register() {
        try {
            $userData = $this->getAuthenticatedUser();
            
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->adminRequired());
            }

            $encryptedData = $this->getEncryptedRequest();
            if (!$encryptedData) {
                return $this->sendResponse($this->validator->invalidRequestFormat());
            }

            $data = $this->decryptCredentials($encryptedData);

            if (!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
                return $this->sendResponse($this->validator->incompleteRegisterData());
            }

            if (!in_array($data['role'], ['admin', 'user'])) {
                return $this->sendResponse($this->validator->invalidRole());
            }

            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $result = $this->repository->crearUsuario($data['username'], $passwordHash, $data['role']);

            if ($result) {
                Logger::info('Usuario creado exitosamente', [
                    'new_username' => $data['username'],
                    'role' => $data['role'],
                    'created_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->registerSuccess($data['username'], $data['role']));
            } else {
                Logger::warning('Error al crear usuario', [
                    'username' => $data['username'],
                    'created_by' => $userData['username']
                ]);
                return $this->sendResponse($this->validator->registerError());
            }

        } catch (Exception $e) {
            Logger::error('Error en registro de usuario', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * Desbloquear cuenta (solo para admins)
     */
    public function unlockAccount() {
        try {
            $userData = $this->getAuthenticatedUser();
            
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->adminRequired());
            }

            $encryptedData = $this->getEncryptedRequest();
            if (!$encryptedData) {
                return $this->sendResponse($this->validator->invalidRequestFormat());
            }

            $data = $this->decryptCredentials($encryptedData);

            if (!$data || !isset($data['username'])) {
                return $this->sendResponse($this->validator->unlockUsernameRequired());
            }

            $result = $this->repository->desbloquearCuenta($data['username']);

            if ($result) {
                Logger::accountUnlocked($data['username'], $userData['username']);
                return $this->sendResponse($this->validator->unlockSuccess($data['username']));
            } else {
                Logger::warning('Intento de desbloquear usuario inexistente', [
                    'username' => $data['username'],
                    'unlocked_by' => $userData['username']
                ]);
                return $this->sendResponse($this->validator->userNotFound());
            }

        } catch (Exception $e) {
            Logger::error('Error al desbloquear cuenta', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * Verificar estado de sesión
     */
    public function checkStatus() {
        try {
            $userData = $this->getAuthenticatedUser();
            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $response = $this->validator->sessionActive();
            $response['data'] = [
                'id' => $userData['id'],
                'username' => $userData['username'],
                'role' => $userData['role']
            ];
            return $this->sendResponse($response);

        } catch (Exception $e) {
            return $this->sendResponse($this->validator->sessionVerifyError());
        }
    }

    /**
     * Cerrar sesión (logout)
     */
    public function logout() {
        try {
            $userData = $this->getAuthenticatedUser();
            $token = $this->extractToken();
            
            if ($token) {
                $invalidated = $this->repository->invalidarSesion($token);
                
                if ($invalidated) {
                    Logger::info('Logout exitoso', [
                        'username' => $userData ? $userData['username'] : 'unknown',
                        'user_id' => $userData ? $userData['id'] : 'unknown',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    
                    return $this->sendResponse($this->validator->logoutSuccess());
                }
            }
            
            return $this->sendResponse($this->validator->logoutGeneric());

        } catch (Exception $e) {
            Logger::error('Error en logout', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->logoutError());
        }
    }

    /**
     * Cerrar todas las sesiones del usuario
     */
    public function logoutAll() {
        try {
            $userData = $this->getAuthenticatedUser();
            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $count = $this->repository->invalidarTodasLasSesiones($userData['id']);
            
            Logger::info('Logout de todas las sesiones', [
                'username' => $userData['username'],
                'user_id' => $userData['id'],
                'sessions_closed' => $count,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $this->sendResponse($this->validator->logoutAllSuccess($count));

        } catch (Exception $e) {
            Logger::error('Error en logout all', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->logoutAllError());
        }
    }

    /**
     * Obtiene todos los usuarios (solo para admins)
     */
    public function getAllUsers() {
        try {
            $userData = $this->getAuthenticatedUser();
            
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->permissionDenied());
            }

            $users = $this->repository->obtenerTodosLosUsuarios();
            
            $formattedUsers = array_map(function($user) {
                return [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'failedAttempts' => (int)$user['failed_attempts'],
                    'lastAttemptTime' => $user['last_attempt_time'],
                    'lockoutUntil' => $user['lockout_until'],
                    'isPermanentlyLocked' => (bool)$user['is_permanently_locked'],
                    'isActive' => !$user['is_permanently_locked']
                ];
            }, $users);

            $response = $this->validator->usersListSuccess();
            $response['data'] = $formattedUsers;
            return $this->sendResponse($response);

        } catch (Exception $e) {
            Logger::error('Error al obtener usuarios', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->usersListError());
        }
    }

    /**
     * Activa o desactiva permanentemente un usuario (solo para admins)
     */
    public function toggleUserStatus($userId) {
        try {
            $userData = $this->getAuthenticatedUser();
            
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->permissionDenied());
            }

            if ($userData['id'] == $userId) {
                return $this->sendResponse($this->validator->cannotDeactivateSelf());
            }

            $result = $this->repository->toggleEstadoUsuario($userId);
            
            if ($result === null) {
                return $this->sendResponse($this->validator->userNotFound());
            }

            $accion = $result ? 'desactivar' : 'activar';
            
            Logger::info("Usuario " . ($result ? 'desactivado' : 'activado'), [
                'user_id' => $userId,
                'action' => $accion,
                'by_admin' => $userData['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $response = $this->validator->toggleStatusSuccess($result);
            $response['data'] = ['isPermanentlyLocked' => $result];
            return $this->sendResponse($response);

        } catch (Exception $e) {
            Logger::error('Error al cambiar estado de usuario', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->toggleStatusError());
        }
    }

    /**
     * Elimina un usuario (solo para admins)
     */
    public function deleteUser($userId) {
        try {
            $userData = $this->getAuthenticatedUser();
            
            if (!$userData || $userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->permissionDenied());
            }

            if ($userData['id'] == $userId) {
                return $this->sendResponse($this->validator->cannotDeleteSelf());
            }

            $result = $this->repository->eliminarUsuario($userId);
            
            if (!$result) {
                return $this->sendResponse($this->validator->deleteUserError());
            }

            Logger::info("Usuario eliminado", [
                'user_id' => $userId,
                'by_admin' => $userData['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $this->sendResponse($this->validator->deleteUserSuccess());

        } catch (Exception $e) {
            Logger::error('Error al eliminar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->deleteError());
        }
    }

    // ============================================================
    // MÉTODOS PRIVADOS DE APOYO
    // ============================================================

    /**
     * Obtiene datos encriptados del request
     */
    private function getEncryptedRequest(): ?array {
        $requestBody = $this->app->request()->getBody();
        $encryptedData = json_decode($requestBody, true);

        if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
            return null;
        }
        
        return $encryptedData;
    }

    /**
     * Desencripta las credenciales
     */
    private function decryptCredentials(array $encryptedData): ?array {
        $decryptedData = $this->decryptData($encryptedData['payload'], $encryptedData['iv']);
        
        if (!$decryptedData) {
            return null;
        }
        
        return json_decode($decryptedData, true);
    }

    /**
     * Verifica si hay bloqueo temporal activo
     */
    private function checkTemporaryLock(array $user, string $username): ?array {
        if (!$user['lockout_until']) {
            return null;
        }

        $db = DB::getInstance()->dbh;
        $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), :lockout_until) as seconds_remaining");
        $stmt->execute([':lockout_until' => $user['lockout_until']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $secondsRemaining = (int)$result['seconds_remaining'];
        
        if ($secondsRemaining > 0) {
            Logger::warning('Intento de login en cuenta bloqueada', [
                'username' => $username,
                'seconds_remaining' => $secondsRemaining,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->validator->temporaryLocked($secondsRemaining);
        }
        
        return null;
    }

    /**
     * Maneja un login exitoso
     */
    private function handleSuccessfulLogin(array $user, string $username) {
        $this->repository->limpiarIntentos($user['id']);

        $usernameDisplay = $user['username_plain'] ?? $username;
        $jwtToken = $this->generateJWTWithUsername($user, $usernameDisplay);

        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $sessionId = $this->repository->crearSesion($user['id'], $jwtToken, $expiresAt);

        Logger::loginAttempt($usernameDisplay, true, null, [
            'role' => $user['role'],
            'user_id' => $user['id'],
            'session_id' => $sessionId
        ]);

        $responseData = [
            'token' => $jwtToken,
            'id' => $user['id'],
            'role' => $user['role'],
            'username' => $usernameDisplay
        ];

        $encryptedResponse = $this->encryptData(json_encode($responseData));
        
        $response = $this->validator->loginSuccess($user['role'], $usernameDisplay);
        $response['data'] = [
            'encrypted' => true,
            'payload' => $encryptedResponse['payload'],
            'iv' => $encryptedResponse['iv']
        ];

        return $this->sendResponse($response);
    }

    /**
     * Extrae el token del header Authorization
     */
    private function extractToken(): ?string {
        $authHeader = $this->app->request()->headers('Authorization');
        if (!$authHeader) {
            $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
        }
        
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }
        
        return null;
    }

    /**
     * Obtiene el usuario autenticado desde el middleware JWT
     */
    private function getAuthenticatedUser(): ?array {
        return isset($this->app->user) ? $this->app->user : null;
    }

    /**
     * Procesa los intentos fallidos con el sistema de bloqueo escalonado
     */
    private function procesarFallo($user) {
        $currentFailures = (int)$user['failed_attempts'];
        
        $db = DB::getInstance()->dbh;
        $nowResult = $db->query("SELECT NOW() as now")->fetch(PDO::FETCH_ASSOC);
        $now = new DateTime($nowResult['now']);
        
        $secondsSinceLastAttempt = 0;
        if ($user['last_attempt_time']) {
            $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, :last_attempt, NOW()) as diff");
            $stmt->execute([':last_attempt' => $user['last_attempt_time']]);
            $diffResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $secondsSinceLastAttempt = (int)$diffResult['diff'];
        }

        $lockoutTime = null;
        $permanentLock = 0;

        // Determinar si reiniciamos el contador
        $resetCounter = false;
        if ($user['last_attempt_time']) {
            if ($secondsSinceLastAttempt > self::LOCKOUT_WINDOW && !$user['lockout_until']) {
                $resetCounter = true;
            }
        }

        $newFailures = $resetCounter ? 1 : $currentFailures + 1;
        
        $attemptsInCurrentLevel = ($newFailures - 1) % self::ATTEMPTS_PER_LEVEL + 1;
        $attemptsRemaining = self::ATTEMPTS_PER_LEVEL - $attemptsInCurrentLevel;
        $blockLevel = ceil($newFailures / self::ATTEMPTS_PER_LEVEL);

        Logger::loginAttempt($user['username_plain'] ?? 'unknown', false, null, [
            'user_id' => $user['id'],
            'attempt_number' => $newFailures,
            'block_level' => $blockLevel
        ]);

        $response = null;

        // Aplicar bloqueo según el nivel alcanzado
        if ($newFailures % self::ATTEMPTS_PER_LEVEL === 0) {
            switch ($blockLevel) {
                case 1:
                    $lockoutTime = (clone $now)->modify('+' . self::FIRST_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    $response = $this->validator->firstLevelLockout();
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'temporal_5min', '5 minutos');
                    break;
                
                case 2:
                    $lockoutTime = (clone $now)->modify('+' . self::SECOND_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    $response = $this->validator->secondLevelLockout();
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'temporal_10min', '10 minutos');
                    break;
                
                default:
                    $permanentLock = 1;
                    $response = $this->validator->permanentLockout();
                    Logger::accountLocked($user['username_plain'] ?? 'unknown', 'permanente', null);
                    break;
            }
        } else {
            $response = $this->validator->wrongPassword($attemptsRemaining);
        }

        $this->repository->actualizarIntentoFallido(
            $user['id'],
            $newFailures,
            $lockoutTime,
            $permanentLock
        );

        return $this->sendResponse($response);
    }

    /**
     * Genera un token JWT con username específico
     */
    private function generateJWTWithUsername($user, $username): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            "iat" => time(),
            "exp" => time() + (60 * 60),
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
     * Encripta datos usando AES-256-CBC
     */
    private function encryptData($data): array {
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
    private function decryptData($encryptedPayload, $iv): ?string {
        try {
            $key = hash('sha256', $this->encryptionKey, true);
            $iv = base64_decode($iv);
            $encrypted = base64_decode($encryptedPayload);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Codifica en Base64 URL-safe
     */
    private function base64UrlEncode($data): string {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Decodifica Base64 URL-safe
     */
    private function base64UrlDecode($data): string {
        $base64 = str_replace(['-', '_'], ['+', '/'], $data);
        return base64_decode($base64);
    }

    /**
     * Envía la respuesta JSON
     */
    private function sendResponse(array $responseData): void {
        $response = $this->app->response();
        $response->header('Content-Type', 'application/json');
        $response->body(json_encode([
            "tipo" => $responseData['tipo'],
            "mensajes" => $responseData['mensajes'],
            "data" => $responseData['data'] ?? null
        ], JSON_UNESCAPED_UNICODE));
    }
}