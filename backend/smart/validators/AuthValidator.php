<?php
class AuthValidator
{
    private $errors = [];

    // ============================================================
    // SECCIÓN: VALIDACIONES DE ENTRADA
    // ============================================================

    public function validateLogin($data): bool
    {
        $this->errors = [];

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

        if (!isset($data['password']) || empty($data['password'])) {
            $this->errors[] = "La contraseña es requerida.";
        } else {
            if (strlen($data['password']) < 6) {
                $this->errors[] = "La contraseña debe tener al menos 6 caracteres.";
            }
        }

        return empty($this->errors);
    }

    public function validateRegister($data): bool
    {
        $this->errors = [];

        // Username
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

        // Password con reglas estrictas
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

        // Role
        if (!isset($data['role']) || empty($data['role'])) {
            $this->errors[] = "El rol es requerido.";
        } else {
            if (!in_array($data['role'], ['admin', 'user'])) {
                $this->errors[] = "El rol debe ser 'admin' o 'user'.";
            }
        }

        return empty($this->errors);
    }

    public function validateUnlock($data): bool
    {
        $this->errors = [];

        if (!isset($data['username']) || empty(trim($data['username']))) {
            $this->errors[] = "El nombre de usuario a desbloquear es requerido.";
        }

        return empty($this->errors);
    }

    public function validateEncryptedPayload($data): bool
    {
        $this->errors = [];

        if (!isset($data['payload']) || empty($data['payload'])) {
            $this->errors[] = "El payload encriptado es requerido.";
        } elseif (base64_decode($data['payload'], true) === false) {
            $this->errors[] = "El payload no tiene un formato base64 válido.";
        }

        if (!isset($data['iv']) || empty($data['iv'])) {
            $this->errors[] = "El vector de inicialización (IV) es requerido.";
        } elseif (base64_decode($data['iv'], true) === false) {
            $this->errors[] = "El IV no tiene un formato base64 válido.";
        }

        return empty($this->errors);
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE LOGIN
    // ============================================================

    public function invalidRequestFormat(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Formato de petición inválido. Se requiere payload encriptado."]
        ];
    }

    public function decryptionError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al procesar los datos de autenticación."]
        ];
    }

    public function incompleteCredentials(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Credenciales incompletas."]
        ];
    }

    public function emptyFields(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Usuario y contraseña son requeridos."]
        ];
    }

    public function invalidCredentials(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Credenciales incorrectas."]
        ];
    }

    public function permanentlyLocked(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => [
                "Su cuenta ha sido bloqueada permanentemente debido a múltiples intentos fallidos.",
                "Contacte al administrador del sistema para desbloquearla."
            ]
        ];
    }

    public function temporaryLocked(int $secondsRemaining): array
    {
        $minutesLeft = floor($secondsRemaining / 60);
        $secondsLeft = $secondsRemaining % 60;

        return [
            'tipo' => 3,
            'mensajes' => [
                "Su cuenta está temporalmente bloqueada. Tiempo restante: {$minutesLeft} minutos y {$secondsLeft} segundos."
            ]
        ];
    }

    public function loginSuccess(string $role, string $username): array
    {
        $message = $this->getWelcomeMessage($role, $username);
        return [
            'tipo' => 1,
            'mensajes' => [$message]
        ];
    }

    public function getWelcomeMessage(string $role, string $username): string
    {
        switch ($role) {
            case 'admin':
                return "¡Bienvenido Administrador {$username}! Tienes acceso completo al sistema.";
            case 'user':
                return "¡Hola {$username}! Has iniciado sesión como usuario.";
            default:
                return "¡Bienvenido {$username}!";
        }
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE BLOQUEO ESCALONADO
    // ============================================================

    public function wrongPassword(int $attemptsRemaining): array
    {
        $mensajes = ["❌ Credenciales incorrectas."];

        if ($attemptsRemaining > 0) {
            $mensajes[] = "Le quedan {$attemptsRemaining} intento(s) antes del bloqueo.";
        }

        return [
            'tipo' => 3,
            'mensajes' => $mensajes
        ];
    }

    public function firstLevelLockout(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => [
                "❌ Credenciales incorrectas.",
                "Ha superado los 3 intentos permitidos.",
                "Su cuenta ha sido bloqueada por 5 minutos."
            ]
        ];
    }

    public function secondLevelLockout(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => [
                "❌ Credenciales incorrectas.",
                "Reincidencia en intentos fallidos detectada.",
                "Su cuenta ha sido bloqueada por 10 minutos."
            ]
        ];
    }

    public function permanentLockout(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => [
                "Su cuenta ha sido bloqueada permanentemente.",
                "Debe contactar al administrador del sistema para recuperar el acceso."
            ]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE REGISTRO
    // ============================================================

    public function adminRequired(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción. Se requiere rol de administrador."]
        ];
    }

    public function incompleteRegisterData(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Datos incompletos para registro."]
        ];
    }

    public function invalidRole(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Rol inválido."]
        ];
    }

    public function registerSuccess(string $username, string $role): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Usuario '{$username}' creado exitosamente con rol '{$role}'."]
        ];
    }

    public function registerError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al crear el usuario. El nombre de usuario podría estar en uso."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE DESBLOQUEO
    // ============================================================

    public function unlockUsernameRequired(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Se requiere el nombre de usuario a desbloquear."]
        ];
    }

    public function unlockSuccess(string $username): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Cuenta '{$username}' desbloqueada exitosamente."]
        ];
    }

    public function userNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Usuario no encontrado."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE SESIÓN
    // ============================================================

    public function invalidSession(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Sesión no válida o expirada."]
        ];
    }

    public function sessionActive(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Sesión activa."]
        ];
    }

    public function logoutSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Sesión cerrada correctamente."]
        ];
    }

    public function logoutGeneric(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Sesión cerrada."]
        ];
    }

    public function logoutAllSuccess(int $count): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Se cerraron {$count} sesión(es) activa(s)."]
        ];
    }

    public function logoutError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al cerrar sesión."]
        ];
    }

    public function logoutAllError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al cerrar sesiones."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE GESTIÓN DE USUARIOS
    // ============================================================

    public function permissionDenied(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción."]
        ];
    }

    public function usersListSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Lista de usuarios obtenida."]
        ];
    }

    public function usersListError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al obtener la lista de usuarios."]
        ];
    }

    public function cannotDeactivateSelf(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No puede desactivar su propia cuenta."]
        ];
    }

    public function cannotDeleteSelf(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No puede eliminar su propia cuenta."]
        ];
    }

    public function toggleStatusSuccess(bool $isLocked): array
    {
        $estado = $isLocked ? 'desactivado' : 'activado';
        return [
            'tipo' => 1,
            'mensajes' => ["Usuario {$estado} exitosamente."]
        ];
    }

    public function toggleStatusError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al cambiar el estado del usuario."]
        ];
    }

    public function deleteUserSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Usuario eliminado exitosamente."]
        ];
    }

    public function deleteUserError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Usuario no encontrado o no se pudo eliminar."]
        ];
    }

    public function deleteError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al eliminar el usuario."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS GENÉRICAS DE ERROR
    // ============================================================

    public function serverError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error interno del servidor."]
        ];
    }

    public function sessionVerifyError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al verificar sesión."]
        ];
    }

    // ============================================================
    // SECCIÓN: UTILIDADES
    // ============================================================

    public function sanitize($input): mixed
    {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }
}