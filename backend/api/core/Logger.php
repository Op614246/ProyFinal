<?php
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger {
    
    private static $instance = null;
    private $logger;
    
    private function __construct() {
        date_default_timezone_set('America/Lima');
        
        $logPath = __DIR__ . '/../logs/app.log';
        
        $this->logger = new MonologLogger('ProyFinal');
        
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat, true, true);
        
        $handler = new RotatingFileHandler($logPath, 30, MonologLogger::DEBUG);
        $handler->setFormatter($formatter);
        
        $this->logger->pushHandler($handler);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getLogger() {
        return $this->logger;
    }
    
    public static function debug($message, array $context = []) {
        self::getInstance()->getLogger()->debug($message, $context);
    }
    
    public static function info($message, array $context = []) {
        self::getInstance()->getLogger()->info($message, $context);
    }
    
    public static function warning($message, array $context = []) {
        self::getInstance()->getLogger()->warning($message, $context);
    }
    
    public static function error($message, array $context = []) {
        self::getInstance()->getLogger()->error($message, $context);
    }
    
    public static function critical($message, array $context = []) {
        self::getInstance()->getLogger()->critical($message, $context);
    }
    
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
    
    public static function accountLocked($username, $lockType, $duration = null) {
        $context = [
            'username' => $username,
            'lock_type' => $lockType,
            'duration' => $duration,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::warning('Cuenta bloqueada', $context);
    }
    
    public static function accountUnlocked($username, $unlockedBy) {
        $context = [
            'username' => $username,
            'unlocked_by' => $unlockedBy,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::info('Cuenta desbloqueada', $context);
    }
    
    public static function securityError($message, array $context = []) {
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        self::error('Error de seguridad: ' . $message, $context);
    }
}
