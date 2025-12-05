<?php
class CryptoHelper {
    private $key;
    private $cipher = 'AES-256-CBC';
    private static $instance = null;
    
    public function __construct($encryptionKey = null) {
        $this->key = $encryptionKey ?? getenv('ENCRYPTION_KEY');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function encrypt($data) {
        $key = hash('sha256', $this->key, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        return [
            'payload' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }
    
    public function decrypt($encryptedPayload, $iv) {
        try {
            $key = hash('sha256', $this->key, true);
            
            $ivDecoded = base64_decode($iv);
            $encryptedDecoded = base64_decode($encryptedPayload);
            
            $decrypted = openssl_decrypt($encryptedDecoded, $this->cipher, $key, OPENSSL_RAW_DATA, $ivDecoded);
            
            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }

    public function encryptDeterministic($data) {
        $key = hash('sha256', $this->key, true);
        
        $iv = substr(hash('sha256', $this->key . '_IV_FIXED', true), 0, 16);
        
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        return base64_encode($encrypted);
    }

    public function decryptDeterministic($encryptedData) {
        try {
            $key = hash('sha256', $this->key, true);
            
            $iv = substr(hash('sha256', $this->key . '_IV_FIXED', true), 0, 16);
            
            $decrypted = openssl_decrypt(base64_decode($encryptedData), $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function generateKey($length = 32) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}
