<?php
/**
 * Logger.php
 * 
 * Configuración de Monolog para logging centralizado
 * Los logs se guardan en /api/logs/app-YYYY-MM-DD.log
 * Se mantienen los últimos 30 días de logs
 * Zona horaria: America/Lima
 */

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger {
    
    private static $instance = null;
    private $logger;
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        // Configurar zona horaria para Lima, Perú
        date_default_timezone_set('America/Lima');
        
        $logPath = __DIR__ . '/../logs/app.log';
        
        // Crear el logger
        $this->logger = new MonologLogger('ProyFinal');
        
        // Formato personalizado: [fecha] canal.nivel: mensaje contexto
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat, true, true);
        
        // Handler para archivo con rotación diaria (mantiene 30 días)
        // Genera archivos como: app-2025-11-27.log, app-2025-11-28.log, etc.
        $handler = new RotatingFileHandler($logPath, 30, MonologLogger::DEBUG);
        $handler->setFormatter($formatter);
        
        $this->logger->pushHandler($handler);
    }
    
    /**
     * Obtener instancia del logger (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener el logger de Monolog
     */
    public function getLogger() {
        return $this->logger;
    }
    
    /**
     * Log de nivel DEBUG
     */
    public static function debug($message, array $context = []) {
        self::getInstance()->getLogger()->debug($message, $context);
    }
    
    /**
     * Log de nivel INFO
     */
    public static function info($message, array $context = []) {
        self::getInstance()->getLogger()->info($message, $context);
    }
    
    /**
     * Log de nivel WARNING
     */
    public static function warning($message, array $context = []) {
        self::getInstance()->getLogger()->warning($message, $context);
    }
    
    /**
     * Log de nivel ERROR
     */
    public static function error($message, array $context = []) {
        self::getInstance()->getLogger()->error($message, $context);
    }
    
    /**
     * Log de nivel CRITICAL
     */
    public static function critical($message, array $context = []) {
        self::getInstance()->getLogger()->critical($message, $context);
    }
    
    /**
     * Log de intento de login
     */
    public static function loginAttempt($username, $success, $ip = null, array $extra = []) {
        $context = array_merge([
            'username' => $username,
            'ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'success' => $success
        ], $extra);
        
        if ($success) {
            self::info('Login exitoso', $context);
        } else {
            self::warning('Intento de login fallido', $context);
        }
    }
    
    /**
     * Log de bloqueo de cuenta
     */
    public static function accountLocked($username, $lockType, $duration = null) {
        $context = [
            'username' => $username,
            'lock_type' => $lockType,
            'duration' => $duration,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::warning('Cuenta bloqueada', $context);
    }
    
    /**
     * Log de desbloqueo de cuenta
     */
    public static function accountUnlocked($username, $unlockedBy) {
        $context = [
            'username' => $username,
            'unlocked_by' => $unlockedBy,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::info('Cuenta desbloqueada', $context);
    }
    
    /**
     * Log de error de seguridad
     */
    public static function securityError($message, array $context = []) {
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        self::error('Error de seguridad: ' . $message, $context);
    }
}
