<?php
/**
 * TaskValidator.php
 * 
 * Validador y generador de respuestas para el módulo de tareas.
 * Agrupa todas las validaciones y mensajes de respuesta.
 */

class TaskValidator
{
    private $errors = [];

    // Constantes de validación
    const MAX_IMAGE_SIZE = 1572864; // 1.5 MB en bytes
    const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    const VALID_STATUSES = ['pending', 'in_process', 'completed', 'incomplete', 'inactive', 'closed'];
    const VALID_PRIORITIES = ['high', 'medium', 'low'];

    // ============================================================
    // SECCIÓN: VALIDACIONES DE ENTRADA
    // ============================================================

    /**
     * Valida los datos para crear una tarea
     */
    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        // Título requerido y validado
        if (!isset($data['title']) || empty(trim($data['title']))) {
            $this->errors[] = "El título de la tarea es requerido.";
        } else {
            $title = trim($data['title']);
            if (strlen($title) < 3) {
                $this->errors[] = "El título debe tener al menos 3 caracteres.";
            }
            if (strlen($title) > 255) {
                $this->errors[] = "El título no puede exceder 255 caracteres.";
            }
        }

        // Descripción opcional pero con límite (validar solo si existe)
        if (isset($data['description']) && !empty($data['description'])) {
            $description = is_string($data['description']) ? $data['description'] : '';
            if (strlen($description) > 1000) {
                $this->errors[] = "La descripción no puede exceder 1000 caracteres.";
            }
        }

        // Prioridad requerida y válida
        if (!isset($data['priority']) || empty($data['priority'])) {
            $this->errors[] = "La prioridad es requerida.";
        } elseif (!in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            $this->errors[] = "La prioridad debe ser 'high', 'medium' o 'low'.";
        }

        // Deadline opcional pero formato válido
        if (isset($data['deadline']) && !empty($data['deadline'])) {
            $deadline = is_string($data['deadline']) ? trim($data['deadline']) : '';
            if (!$this->isValidDate($deadline)) {
                $this->errors[] = "El formato de fecha límite es inválido. Use YYYY-MM-DD.";
            }
        }

        // Usuario asignado opcional (ID numérico)
        if (isset($data['assigned_user_id']) && !empty($data['assigned_user_id'])) {
            if (!is_numeric($data['assigned_user_id'])) {
                $this->errors[] = "El ID de usuario asignado debe ser numérico.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida los datos para asignar una tarea
     */
    public function validateAssign(array $data): bool
    {
        $this->errors = [];

        // Para reasignación admin, se requiere user_id
        if (isset($data['user_id']) && !empty($data['user_id'])) {
            if (!is_numeric($data['user_id'])) {
                $this->errors[] = "El ID de usuario debe ser numérico.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida el archivo de imagen para completar tarea
     */
    public function validateCompletionImage(array $file): bool
    {
        $this->errors = [];

        // Verificar que existe el archivo
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = "La imagen de evidencia es requerida para completar la tarea.";
            return false;
        }

        // Verificar que el archivo se subió sin errores
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $this->errors[] = $this->getUploadErrorMessage($errorCode);
            return false;
        }

        // Verificar que existe el archivo temporal
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = "El archivo no es una subida válida.";
            return false;
        }

        // Validar tamaño (máximo 1.5 MB)
        $fileSize = filesize($file['tmp_name']);
        if ($fileSize === false || $fileSize > self::MAX_IMAGE_SIZE) {
            $sizeMB = number_format(self::MAX_IMAGE_SIZE / 1048576, 1);
            $this->errors[] = "La imagen no puede exceder {$sizeMB} MB.";
        }

        // Validar tipo MIME real (no confiar en extensión ni en file['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $this->errors[] = "Solo se permiten imágenes JPEG o PNG.";
        }

        return empty($this->errors);
    }

    /**
     * Valida que una fecha tenga formato correcto (YYYY-MM-DD)
     */
    private function isValidDate(string $date): bool
    {
        // Verificar longitud
        if (strlen($date) !== 10) {
            return false;
        }

        // Verificar formato básico YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        // Separar componentes
        $parts = explode('-', $date);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];

        // Validar que sea una fecha válida usando checkdate
        return checkdate($month, $day, $year);
    }

    /**
     * Obtiene mensaje de error de subida de archivo
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño máximo permitido por el servidor.",
            UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño máximo permitido por el formulario.",
            UPLOAD_ERR_PARTIAL => "El archivo solo se subió parcialmente.",
            UPLOAD_ERR_NO_FILE => "No se seleccionó ningún archivo.",
            UPLOAD_ERR_NO_TMP_DIR => "Error del servidor: carpeta temporal faltante.",
            UPLOAD_ERR_CANT_WRITE => "Error del servidor: no se pudo escribir el archivo.",
            UPLOAD_ERR_EXTENSION => "Una extensión de PHP detuvo la subida del archivo."
        ];

        return $errors[$errorCode] ?? "Error desconocido al subir el archivo.";
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE LISTADO
    // ============================================================

    /**
     * Respuesta de lista obtenida exitosamente
     */
    public function listSuccess(int $count): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Se obtuvieron {$count} tarea(s)."]
        ];
    }

    /**
     * Respuesta de lista vacía
     */
    public function listEmpty(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["No hay tareas disponibles."]
        ];
    }

    /**
     * Error al obtener lista
     */
    public function listError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al obtener la lista de tareas."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE CREACIÓN
    // ============================================================

    /**
     * Tarea creada exitosamente
     */
    public function createSuccess(string $title): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea '{$title}' creada exitosamente."]
        ];
    }

    /**
     * Error al crear tarea
     */
    public function createError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al crear la tarea."]
        ];
    }

    /**
     * Datos de creación inválidos
     */
    public function createValidationError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => $this->errors
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ASIGNACIÓN
    // ============================================================

    /**
     * Tarea asignada exitosamente
     */
    public function assignSuccess(string $username): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea asignada a '{$username}' exitosamente."]
        ];
    }

    /**
     * Auto-asignación exitosa
     */
    public function selfAssignSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Te has asignado la tarea exitosamente."]
        ];
    }

    /**
     * Error: tarea ya asignada
     */
    public function alreadyAssigned(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Esta tarea ya está asignada a otro usuario."]
        ];
    }

    /**
     * Error: tarea no encontrada
     */
    public function taskNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Tarea no encontrada."]
        ];
    }

    /**
     * Error: usuario no encontrado
     */
    public function userNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Usuario no encontrado."]
        ];
    }

    /**
     * Error al asignar
     */
    public function assignError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al asignar la tarea."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE COMPLETAR TAREA
    // ============================================================

    /**
     * Tarea completada exitosamente
     */
    public function completeSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea completada exitosamente."]
        ];
    }

    /**
     * Error: tarea no asignada al usuario
     */
    public function notAssignedToYou(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Solo puedes completar tareas que te están asignadas."]
        ];
    }

    /**
     * Error: tarea ya completada
     */
    public function alreadyCompleted(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Esta tarea ya fue completada."]
        ];
    }

    /**
     * Error: estado no permite completar
     */
    public function cannotComplete(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["El estado actual de la tarea no permite completarla."]
        ];
    }

    /**
     * Error de validación de imagen
     */
    public function imageValidationError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => $this->errors
        ];
    }

    /**
     * Error al subir imagen
     */
    public function imageUploadError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al guardar la imagen de evidencia."]
        ];
    }

    /**
     * Error al completar tarea
     */
    public function completeError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al completar la tarea."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE PERMISOS
    // ============================================================

    /**
     * Solo admin puede realizar la acción
     */
    public function adminRequired(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción. Se requiere rol de administrador."]
        ];
    }

    /**
     * Permiso denegado genérico
     */
    public function permissionDenied(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción."]
        ];
    }

    /**
     * Sesión inválida
     */
    public function invalidSession(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Sesión no válida o expirada."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ACTUALIZACIÓN DE ESTADO
    // ============================================================

    /**
     * Estado actualizado exitosamente
     */
    public function statusUpdateSuccess(string $newStatus): array
    {
        $statusLabels = [
            'pending' => 'Pendiente',
            'in_process' => 'En proceso',
            'completed' => 'Completada',
            'incomplete' => 'Incompleta',
            'inactive' => 'Inactiva',
            'closed' => 'Cerrada'
        ];
        $label = $statusLabels[$newStatus] ?? $newStatus;
        
        return [
            'tipo' => 1,
            'mensajes' => ["Estado de tarea actualizado a '{$label}'."]
        ];
    }

    /**
     * Error: estado inválido
     */
    public function invalidStatus(): array
    {
        $validStatuses = implode(', ', self::VALID_STATUSES);
        return [
            'tipo' => 3,
            'mensajes' => ["Estado inválido. Los estados válidos son: {$validStatuses}."]
        ];
    }

    /**
     * Error al actualizar estado
     */
    public function statusUpdateError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al actualizar el estado de la tarea."]
        ];
    }

    /**
     * Valida que el estado sea válido
     */
    public function validateStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES);
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ELIMINACIÓN
    // ============================================================

    /**
     * Tarea eliminada exitosamente
     */
    public function deleteSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea eliminada exitosamente."]
        ];
    }

    /**
     * Error al eliminar tarea
     */
    public function deleteError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al eliminar la tarea."]
        ];
    }

    /**
     * No se puede eliminar tarea completada
     */
    public function cannotDeleteCompleted(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No se puede eliminar una tarea completada."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS GENÉRICAS
    // ============================================================

    /**
     * Error interno del servidor
     */
    public function serverError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error interno del servidor."]
        ];
    }

    /**
     * Formato de petición inválido
     */
    public function invalidRequestFormat(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Formato de petición inválido."]
        ];
    }

    /**
     * Datos incompletos
     */
    public function incompleteData(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Datos incompletos para la operación."]
        ];
    }

    // ============================================================
    // SECCIÓN: UTILIDADES
    // ============================================================

    /**
     * Sanitiza un string para prevenir XSS
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
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Verifica si hay errores
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Agrega un error manualmente
     */
    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Limpia los errores
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
