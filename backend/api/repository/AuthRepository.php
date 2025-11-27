<?php
/**
 * AuthRepository.php
 * 
 * Repositorio para operaciones de autenticación en la base de datos
 * Con soporte para username encriptado
 */

require_once __DIR__ . '/../core/CryptoHelper.php';

class AuthRepository {

    private $db;
    private $crypto;

    public function __construct() {
        // Obtenemos la conexión del Singleton global
        $this->db = DB::getInstance()->dbh;
        $this->crypto = CryptoHelper::getInstance();
    }

    /**
     * Encripta el username para almacenamiento/búsqueda en BD
     */
    private function encryptUsername($username) {
        return $this->crypto->encryptDeterministic(strtolower(trim($username)));
    }

    /**
     * Desencripta el username almacenado en BD
     */
    private function decryptUsername($encryptedUsername) {
        return $this->crypto->decryptDeterministic($encryptedUsername);
    }

    /**
     * Busca un usuario y sus metadatos de seguridad por nombre de usuario.
     * El username se busca encriptado en la BD.
     * 
     * @param string $username Nombre de usuario (en texto plano)
     * @return array|false Datos del usuario o false si no existe
     */
    public function obtenerUsuarioPorUsername($username) {
        // Encriptar el username para buscar en BD
        $encryptedUsername = $this->encryptUsername($username);
        
        $sql = "SELECT id, username, password_hash, role, failed_attempts, 
                       last_attempt_time, lockout_until, is_permanently_locked 
                FROM users WHERE username = :username LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':username', $encryptedUsername, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Desencriptar el username para uso interno
            $user['username_plain'] = $this->decryptUsername($user['username']);
        }
        
        return $user;
    }

    /**
     * Registra un intento fallido y actualiza los bloqueos.
     * 
     * @param int $userId ID del usuario
     * @param int $nuevosFallos Número de intentos fallidos acumulados
     * @param string|null $tiempoBloqueo Fecha/hora hasta cuando está bloqueado
     * @param int $bloqueoPermanente 1 si está bloqueado permanentemente, 0 si no
     */
    public function actualizarIntentoFallido($userId, $nuevosFallos, $tiempoBloqueo, $bloqueoPermanente) {
        $sql = "UPDATE users SET 
                failed_attempts = :fails, 
                last_attempt_time = NOW(), 
                lockout_until = :lockout, 
                is_permanently_locked = :perm 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fails'   => $nuevosFallos,
            ':lockout' => $tiempoBloqueo, // Puede ser NULL
            ':perm'    => $bloqueoPermanente, // 0 o 1
            ':id'      => $userId
        ]);
    }

    /**
     * Limpia el historial de fallos tras un login exitoso.
     * 
     * @param int $userId ID del usuario
     */
    public function limpiarIntentos($userId) {
        $sql = "UPDATE users SET 
                failed_attempts = 0, 
                last_attempt_time = NULL, 
                lockout_until = NULL 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Crea un nuevo usuario en la base de datos.
     * El username se guarda encriptado.
     * 
     * @param string $username Nombre de usuario (texto plano)
     * @param string $passwordHash Hash de la contraseña (ya hasheado con password_hash)
     * @param string $role Rol del usuario ('admin' o 'user')
     * @return bool True si se creó exitosamente, false si hubo error
     */
    public function crearUsuario($username, $passwordHash, $role) {
        try {
            // Encriptar el username antes de guardar
            $encryptedUsername = $this->encryptUsername($username);
            
            $sql = "INSERT INTO users (username, password_hash, role, failed_attempts, is_permanently_locked) 
                    VALUES (:username, :password_hash, :role, 0, 0)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $encryptedUsername,
                ':password_hash' => $passwordHash,
                ':role' => $role
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Si es un error de duplicado (username ya existe)
            if ($e->getCode() == 23000) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Desbloquea una cuenta de usuario (solo admin puede llamar esto).
     * Resetea todos los campos de bloqueo.
     * 
     * @param string $username Nombre de usuario a desbloquear (texto plano)
     * @return bool True si se desbloqueó, false si el usuario no existe
     */
    public function desbloquearCuenta($username) {
        // Encriptar el username para buscar en BD
        $encryptedUsername = $this->encryptUsername($username);
        
        $sql = "UPDATE users SET 
                failed_attempts = 0, 
                last_attempt_time = NULL, 
                lockout_until = NULL, 
                is_permanently_locked = 0 
                WHERE username = :username";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $encryptedUsername]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si un usuario existe.
     * 
     * @param string $username Nombre de usuario (texto plano)
     * @return bool True si existe, false si no
     */
    public function usuarioExiste($username) {
        $encryptedUsername = $this->encryptUsername($username);
        
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $encryptedUsername]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene todos los usuarios (para panel de administración).
     * Desencripta los usernames para mostrarlos.
     * No incluye el password_hash por seguridad.
     * 
     * @return array Lista de usuarios con username desencriptado
     */
    public function obtenerTodosLosUsuarios() {
        $sql = "SELECT id, username, role, failed_attempts, last_attempt_time, 
                       lockout_until, is_permanently_locked 
                FROM users ORDER BY id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Desencriptar cada username
        foreach ($users as &$user) {
            $user['username_encrypted'] = $user['username'];
            $user['username'] = $this->decryptUsername($user['username']);
        }
        
        return $users;
    }
}