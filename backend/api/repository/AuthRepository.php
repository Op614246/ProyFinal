<?php
require_once __DIR__ . '/../core/CryptoHelper.php';

class AuthRepository {

    private $db;
    private $crypto;

    public function __construct() {
        $this->db = DB::getInstance()->dbh;
        $this->crypto = CryptoHelper::getInstance();
    }

    private function encryptUsername($username) {
        return $this->crypto->encryptDeterministic(strtolower(trim($username)));
    }

    private function decryptUsername($encryptedUsername) {
        return $this->crypto->decryptDeterministic($encryptedUsername);
    }

    public function obtenerUsuarioPorUsername($username) {
        // Encriptar el username para buscar en BD
        $encryptedUsername = $this->encryptUsername($username);
        
        $sql = "SELECT id, username, password_hash, role, failed_attempts, 
                       last_attempt_time, lockout_until, is_permanently_locked 
                FROM users WHERE username = :username LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':username', $encryptedUsername, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user) {
            $user['username_plain'] = $this->decryptUsername($user['username']);
        }
        
        return $user;
    }

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

    public function limpiarIntentos($userId) {
        $sql = "UPDATE users SET 
                failed_attempts = 0, 
                last_attempt_time = NULL, 
                lockout_until = NULL 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
    }

    public function crearUsuario($username, $passwordHash, $role) {
        try {
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
            if ($e->getCode() == 23000) {
                return false;
            }
            throw $e;
        }
    }

    public function desbloquearCuenta($username) {
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

    public function usuarioExiste($username) {
        $encryptedUsername = $this->encryptUsername($username);
        
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $encryptedUsername]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function obtenerTodosLosUsuarios() {
        $sql = "SELECT id, username, role, failed_attempts, last_attempt_time, 
                       lockout_until, is_permanently_locked 
                FROM users ORDER BY id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $users = $stmt->fetchAll();
        
        foreach ($users as &$user) {
            $user['username_encrypted'] = $user['username'];
            $user['username'] = $this->decryptUsername($user['username']);
        }
        
        return $users;
    }

    public function toggleEstadoUsuario($userId) {
        $sql = "SELECT is_permanently_locked FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        $nuevoEstado = $user['is_permanently_locked'] ? 0 : 1;
        
        if ($nuevoEstado === 0) {
            $sql = "UPDATE users SET 
                    is_permanently_locked = 0,
                    failed_attempts = 0,
                    last_attempt_time = NULL,
                    lockout_until = NULL
                    WHERE id = :id";
        } else {
            $sql = "UPDATE users SET is_permanently_locked = 1 WHERE id = :id";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        
        return (bool)$nuevoEstado;
    }

    public function eliminarUsuario($userId) {
        $sql = "DELETE FROM user_sessions WHERE user_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        
        return $stmt->rowCount() > 0;
    }

    public function obtenerUsuarioPorId($userId) {
        $sql = "SELECT id, username, role, failed_attempts, last_attempt_time, 
                       lockout_until, is_permanently_locked 
                FROM users WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['username'] = $this->decryptUsername($user['username']);
        }
        
        return $user;
    }

    public function crearSesion($userId, $token, $expiresAt) {
        $tokenHash = hash('sha256', $token);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        
        $sql = "INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) 
                VALUES (:user_id, :token_hash, :ip, :user_agent, :expires_at)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':ip' => $ip,
            ':user_agent' => $userAgent,
            ':expires_at' => $expiresAt
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }

    public function invalidarSesion($token) {
        $tokenHash = hash('sha256', $token);
        
        $sql = "UPDATE user_sessions 
                SET is_active = 0, logged_out_at = NOW() 
                WHERE token_hash = :token_hash AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token_hash' => $tokenHash]);
        
        return $stmt->rowCount() > 0;
    }

    public function invalidarTodasLasSesiones($userId) {
        $sql = "UPDATE user_sessions 
                SET is_active = 0, logged_out_at = NOW() 
                WHERE user_id = :user_id AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->rowCount();
    }

    public function sesionActiva($token) {
        $tokenHash = hash('sha256', $token);
        
        $sql = "SELECT COUNT(*) FROM user_sessions 
                WHERE token_hash = :token_hash 
                AND is_active = 1 
                AND expires_at > NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token_hash' => $tokenHash]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function obtenerHistorialSesiones($userId, $limit = 10) {
        $sql = "SELECT id, ip_address, user_agent, created_at, expires_at, 
                       logged_out_at, is_active 
                FROM user_sessions 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function limpiarSesionesExpiradas() {
        $sql = "UPDATE user_sessions 
                SET is_active = 0 
                WHERE is_active = 1 AND expires_at < NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}