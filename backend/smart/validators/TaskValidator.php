<?php
class TaskValidator
{
    private $errors = [];

    public function validateCreate(array $data): bool
    {
        $this->errors = [];

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

        if (isset($data['description']) && !empty($data['description'])) {
            $description = is_string($data['description']) ? $data['description'] : '';
            if (strlen($description) > 1000) {
                $this->errors[] = "La descripción no puede exceder 1000 caracteres.";
            }
        }

        if (!isset($data['priority']) || empty($data['priority'])) {
            $this->errors[] = "La prioridad es requerida.";
        } elseif (!in_array($data['priority'], TaskConfig::PRIORITIES, true)) {
            $this->errors[] = "La prioridad debe ser 'high', 'medium' o 'low'.";
        }

        if (isset($data['deadline']) && !empty($data['deadline'])) {
            $deadline = is_string($data['deadline']) ? trim($data['deadline']) : '';
            if (!$this->isValidDate($deadline)) {
                $this->errors[] = "El formato de fecha límite es inválido. Use YYYY-MM-DD.";
            } else {
                // Validar que el deadline sea mayor que la fecha de asignación
                $fechaAsignacion = $data['fecha_asignacion'] ?? date('Y-m-d');
                $errorMsg = null;
                if (!self::validateDeadlineAfterOrEqual($fechaAsignacion, $deadline, $errorMsg)) {
                    $this->errors[] = $errorMsg;
                }
            }
        }

        if (isset($data['assigned_user_id']) && !empty($data['assigned_user_id'])) {
            if (!is_numeric($data['assigned_user_id'])) {
                $this->errors[] = "El ID de usuario asignado debe ser numérico.";
            }
        }

        return empty($this->errors);
    }

    public function validateAssign(array $data): bool
    {
        $this->errors = [];

        if (isset($data['user_id']) && !empty($data['user_id'])) {
            if (!is_numeric($data['user_id'])) {
                $this->errors[] = "El ID de usuario debe ser numérico.";
            }
        }

        // Validar que el deadline sea mayor que la fecha de asignación si ambos están presentes
        if (isset($data['deadline']) && !empty($data['deadline']) && isset($data['fecha_asignacion']) && !empty($data['fecha_asignacion'])) {
            $errorMsg = null;
            if (!self::validateDeadlineAfterOrEqual($data['fecha_asignacion'], $data['deadline'], $errorMsg)) {
                $this->errors[] = $errorMsg;
            }
        }

        return empty($this->errors);
    }

    public function validateCompletionImage(array $file): bool
    {
        $this->errors = [];

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = "La imagen de evidencia es requerida para completar la tarea.";
            return false;
        }

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $this->errors[] = $this->getUploadErrorMessage($errorCode);
            return false;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = "El archivo no es una subida válida.";
            return false;
        }

        $fileSize = filesize($file['tmp_name']);
        if ($fileSize === false || $fileSize > TaskConfig::MAX_FILE_SIZE_BYTES) {
            $sizeMB = number_format(TaskConfig::MAX_FILE_SIZE_BYTES / 1048576, 1);
            $this->errors[] = "La imagen no puede exceder {$sizeMB} MB.";
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$mimeType || !in_array($mimeType, TaskConfig::ALLOWED_MIME_TYPES, true)) {
            $this->errors[] = "Solo se permiten imágenes JPEG o PNG.";
        }

        return empty($this->errors);
    }

    private function isValidDate(string $date): bool
    {
        if (strlen($date) !== 10) {
            return false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];

        return checkdate($month, $day, $year);
    }

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

    public function listSuccess(int $count): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Se obtuvieron {$count} tarea(s)."]
        ];
    }

    public function listEmpty(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["No hay tareas disponibles."]
        ];
    }

    public function listError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al obtener la lista de tareas."]
        ];
    }

    public function createSuccess(string $title): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea '{$title}' creada exitosamente."]
        ];
    }

    public function createError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al crear la tarea."]
        ];
    }

    public function createValidationError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => $this->errors
        ];
    }

    public function assignSuccess(string $username): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea asignada a '{$username}' exitosamente."]
        ];
    }

    public function selfAssignSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Te has asignado la tarea exitosamente."]
        ];
    }

    public function alreadyAssigned(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Esta tarea ya está asignada a otro usuario."]
        ];
    }

    public function taskNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Tarea no encontrada."]
        ];
    }

    public function userNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Usuario no encontrado."]
        ];
    }

    public function assignError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al asignar la tarea."]
        ];
    }

    public function completeSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea completada exitosamente."]
        ];
    }

    public function notAssignedToYou(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Solo puedes completar tareas que te están asignadas."]
        ];
    }

    public function alreadyCompleted(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Esta tarea ya fue completada."]
        ];
    }

    public function cannotComplete(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["El estado actual de la tarea no permite completarla."]
        ];
    }

    public function imageValidationError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => $this->errors
        ];
    }

    public function imageUploadError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al guardar la imagen de evidencia."]
        ];
    }

    public function completeError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al completar la tarea."]
        ];
    }

    public function adminRequired(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción. Se requiere rol de administrador."]
        ];
    }

    public function permissionDenied(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No tiene permisos para realizar esta acción."]
        ];
    }

    public function invalidSession(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Sesión no válida o expirada."]
        ];
    }

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

    public function invalidStatus(): array
    {
        $validStatuses = implode(', ', TaskConfig::STATUSES);
        return [
            'tipo' => 3,
            'mensajes' => ["Estado inválido. Los estados válidos son: {$validStatuses}."]
        ];
    }

    public function statusUpdateError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al actualizar el estado de la tarea."]
        ];
    }

    public function validateStatus(string $status): bool
    {
        return in_array($status, TaskConfig::STATUSES);
    }

    public static function validateDeadlineAfterOrEqual(string $fechaAsignacion, string $deadline, ?string &$error = null): bool
    {
        try {
            $fa = new DateTime($fechaAsignacion);
            $dl = new DateTime($deadline);
        } catch (Exception $e) {
            $error = 'Formato de fecha inválido.';
            return false;
        }

        if ($dl < $fa) {
            $error = 'El plazo (deadline) no puede ser anterior a la fecha de asignación.';
            return false;
        }

        return true;
    }

    public function deleteSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Tarea eliminada exitosamente."]
        ];
    }

    public function deleteError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al eliminar la tarea."]
        ];
    }

    public function cannotDeleteCompleted(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No se puede eliminar una tarea completada."]
        ];
    }

    public function serverError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error interno del servidor."]
        ];
    }

    public function invalidRequestFormat(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Formato de petición inválido."]
        ];
    }

    public function incompleteData(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Datos incompletos para la operación."]
        ];
    }

    public function sanitize($input)
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
