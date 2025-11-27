<?php
/**
 * CryptoHelper.php
 * 
 * Clase de utilidad para encriptación/desencriptación AES-256-CBC
 * Compatible con CryptoJS en el frontend
 */

class CryptoHelper {
    
    private $key;
    private $cipher = 'AES-256-CBC';
    private static $instance = null;
    
    /**
     * Constructor
     * 
     * @param string $encryptionKey Clave de encriptación del .env
     */
    public function __construct($encryptionKey = null) {
        $this->key = $encryptionKey ?? getenv('ENCRYPTION_KEY');
    }

    /**
     * Obtener instancia singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Encripta datos usando AES-256-CBC (con IV aleatorio)
     * Usar para datos en tránsito
     * 
     * @param string $data Datos a encriptar
     * @return array ['payload' => string, 'iv' => string]
     */
    public function encrypt($data) {
        // Generar clave de 256 bits desde la clave de encriptación
        $key = hash('sha256', $this->key, true);
        
        // Generar IV aleatorio de 16 bytes
        $iv = openssl_random_pseudo_bytes(16);
        
        // Encriptar
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        return [
            'payload' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }
    
    /**
     * Desencripta datos usando AES-256-CBC
     * 
     * @param string $encryptedPayload Datos encriptados en base64
     * @param string $iv Vector de inicialización en base64
     * @return string|false Datos desencriptados o false si falla
     */
    public function decrypt($encryptedPayload, $iv) {
        try {
            // Generar clave de 256 bits desde la clave de encriptación
            $key = hash('sha256', $this->key, true);
            
            // Decodificar base64
            $ivDecoded = base64_decode($iv);
            $encryptedDecoded = base64_decode($encryptedPayload);
            
            // Desencriptar
            $decrypted = openssl_decrypt($encryptedDecoded, $this->cipher, $key, OPENSSL_RAW_DATA, $ivDecoded);
            
            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Encripta datos de forma DETERMINÍSTICA (mismo input = mismo output)
     * Usar para datos que necesitan ser buscados en BD (como username)
     * 
     * @param string $data Datos a encriptar
     * @return string Datos encriptados en base64
     */
    public function encryptDeterministic($data) {
        $key = hash('sha256', $this->key, true);
        
        // IV fijo derivado de la clave (para búsquedas determinísticas)
        $iv = substr(hash('sha256', $this->key . '_IV_FIXED', true), 0, 16);
        
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        return base64_encode($encrypted);
    }

    /**
     * Desencripta datos encriptados de forma determinística
     * 
     * @param string $encryptedData Datos encriptados en base64
     * @return string|false Datos desencriptados o false si falla
     */
    public function decryptDeterministic($encryptedData) {
        try {
            $key = hash('sha256', $this->key, true);
            
            // IV fijo (el mismo usado en encryptDeterministic)
            $iv = substr(hash('sha256', $this->key . '_IV_FIXED', true), 0, 16);
            
            $decrypted = openssl_decrypt(base64_decode($encryptedData), $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Genera una clave de encriptación segura
     * 
     * @param int $length Longitud de la clave en bytes
     * @return string Clave en formato hexadecimal
     */
    public static function generateKey($length = 32) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}
