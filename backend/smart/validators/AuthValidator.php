<?php
/**
 * AuthValidator.php
 * 
 * Validador para datos de autenticación
 */

class AuthValidator
{
    private $errors = [];

    /**
     * Valida las credenciales de login
     * 
     * @param array $data Datos a validar ['username' => '', 'password' => '']
     * @return bool True si es válido, false si hay errores
     */
    public function validateLogin($data)
    {
        $this->errors = [];

        // Validar username
        if (!isset($data['username']) || empty(trim($data['username']))) {
            $this->errors[] = "El nombre de usuario es requerido.";
        } else {
            $username = trim($data['username']);
            
            if (strlen($username) < 3) {
                $this->errors[] = "El nombre de usuario debe tener al menos 3 caracteres.";
            }
            
            if (strlen($username) > 50) {
                $this->errors[] = "El nombre de usuario no puede exceder 50 caracteres.";
            }
            
            // Solo alfanuméricos y guion bajo
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $this->errors[] = "El nombre de usuario solo puede contener letras, números y guion bajo.";
            }
        }

        // Validar password
        if (!isset($data['password']) || empty($data['password'])) {
            $this->errors[] = "La contraseña es requerida.";
        } else {
            if (strlen($data['password']) < 6) {
                $this->errors[] = "La contraseña debe tener al menos 6 caracteres.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida los datos para registro de usuario
     * 
     * @param array $data Datos a validar ['username' => '', 'password' => '', 'role' => '']
     * @return bool True si es válido, false si hay errores
     */
    public function validateRegister($data)
    {
        $this->errors = [];

        // Validar username
        if (!isset($data['username']) || empty(trim($data['username']))) {
            $this->errors[] = "El nombre de usuario es requerido.";
        } else {
            $username = trim($data['username']);
            
            if (strlen($username) < 3) {
                $this->errors[] = "El nombre de usuario debe tener al menos 3 caracteres.";
            }
            
            if (strlen($username) > 50) {
                $this->errors[] = "El nombre de usuario no puede exceder 50 caracteres.";
            }
            
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $this->errors[] = "El nombre de usuario solo puede contener letras, números y guion bajo.";
            }
        }

        // Validar password con reglas más estrictas
        if (!isset($data['password']) || empty($data['password'])) {
            $this->errors[] = "La contraseña es requerida.";
        } else {
            $password = $data['password'];
            
            if (strlen($password) < 8) {
                $this->errors[] = "La contraseña debe tener al menos 8 caracteres.";
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                $this->errors[] = "La contraseña debe contener al menos una letra mayúscula.";
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                $this->errors[] = "La contraseña debe contener al menos una letra minúscula.";
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                $this->errors[] = "La contraseña debe contener al menos un número.";
            }
            
            if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
                $this->errors[] = "La contraseña debe contener al menos un carácter especial.";
            }
        }

        // Validar role
        if (!isset($data['role']) || empty($data['role'])) {
            $this->errors[] = "El rol es requerido.";
        } else {
            if (!in_array($data['role'], ['admin', 'user'])) {
                $this->errors[] = "El rol debe ser 'admin' o 'user'.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida datos para desbloqueo de cuenta
     * 
     * @param array $data Datos a validar ['username' => '']
     * @return bool True si es válido, false si hay errores
     */
    public function validateUnlock($data)
    {
        $this->errors = [];

        if (!isset($data['username']) || empty(trim($data['username']))) {
            $this->errors[] = "El nombre de usuario a desbloquear es requerido.";
        }

        return empty($this->errors);
    }

    /**
     * Valida que el payload encriptado tenga el formato correcto
     * 
     * @param array $data Datos a validar ['payload' => '', 'iv' => '']
     * @return bool True si es válido, false si hay errores
     */
    public function validateEncryptedPayload($data)
    {
        $this->errors = [];

        if (!isset($data['payload']) || empty($data['payload'])) {
            $this->errors[] = "El payload encriptado es requerido.";
        } else {
            // Verificar que sea base64 válido
            if (base64_decode($data['payload'], true) === false) {
                $this->errors[] = "El payload no tiene un formato base64 válido.";
            }
        }

        if (!isset($data['iv']) || empty($data['iv'])) {
            $this->errors[] = "El vector de inicialización (IV) es requerido.";
        } else {
            // Verificar que sea base64 válido
            if (base64_decode($data['iv'], true) === false) {
                $this->errors[] = "El IV no tiene un formato base64 válido.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Sanitiza un string para prevenir XSS
     * 
     * @param string $input String a sanitizar
     * @return string String sanitizado
     */
    public function sanitize($input)
    {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    /**
     * Obtiene los errores de validación
     * 
     * @return array Array de mensajes de error
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Verifica si hay errores
     * 
     * @return bool True si hay errores, false si no
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Agrega un error manualmente
     * 
     * @param string $error Mensaje de error
     */
    public function addError($error)
    {
        $this->errors[] = $error;
    }
}
