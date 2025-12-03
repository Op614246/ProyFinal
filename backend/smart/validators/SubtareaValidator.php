<?php
/**
 * SubtareaValidator.php
 * 
 * Validador y generador de respuestas para el módulo de subtareas.
 * Las subtareas son las unidades de trabajo que se completan realmente.
 * Usa TaskConfig para configuración centralizada.
 */

require_once __DIR__ . '/../../api/core/TaskConfig.php';

class SubtareaValidator
{
    private $errors = [];

    // ============================================================
    // SECCIÓN: VALIDACIONES DE ENTRADA
    // ============================================================

    /**
     * Valida los datos para crear una subtarea
     */
    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        // task_id requerido
        if (!isset($data['task_id']) || empty($data['task_id'])) {
            $this->errors[] = "El ID de la tarea padre es requerido.";
        } elseif (!is_numeric($data['task_id'])) {
            $this->errors[] = "El ID de la tarea padre debe ser numérico.";
        }

        // Título requerido y validado
        if (!isset($data['titulo']) || empty(trim($data['titulo']))) {
            $this->errors[] = "El título de la subtarea es requerido.";
        } else {
            $titulo = trim($data['titulo']);
            if (strlen($titulo) < 3) {
                $this->errors[] = "El título debe tener al menos 3 caracteres.";
            }
            if (strlen($titulo) > 250) {
                $this->errors[] = "El título no puede exceder 250 caracteres.";
            }
        }

        // Descripción opcional pero con límite
        if (isset($data['descripcion']) && !empty($data['descripcion'])) {
            $descripcion = is_string($data['descripcion']) ? $data['descripcion'] : '';
            if (strlen($descripcion) > 1000) {
                $this->errors[] = "La descripción no puede exceder 1000 caracteres.";
            }
        }

        // Estado opcional pero válido (usando TaskConfig)
        if (isset($data['estado']) && !empty($data['estado'])) {
            if (!TaskConfig::isValidSubtareaEstado($data['estado'])) {
                $validEstados = implode(', ', TaskConfig::SUBTAREA_ESTADOS);
                $this->errors[] = "El estado debe ser: {$validEstados}.";
            }
        }

        // Prioridad opcional pero válida (usando TaskConfig)
        if (isset($data['prioridad']) && !empty($data['prioridad'])) {
            if (!TaskConfig::isValidSubtareaPrioridad($data['prioridad'])) {
                $validPrioridades = implode(', ', TaskConfig::SUBTAREA_PRIORIDADES);
                $this->errors[] = "La prioridad debe ser: {$validPrioridades}.";
            }
        }

        // Fechas opcionales pero formato válido
        if (isset($data['fechaAsignacion']) && !empty($data['fechaAsignacion'])) {
            if (!$this->isValidDate($data['fechaAsignacion'])) {
                $this->errors[] = "El formato de fecha de asignación es inválido. Use YYYY-MM-DD.";
            }
        }

        if (isset($data['fechaVencimiento']) && !empty($data['fechaVencimiento'])) {
            if (!$this->isValidDate($data['fechaVencimiento'])) {
                $this->errors[] = "El formato de fecha de vencimiento es inválido. Use YYYY-MM-DD.";
            }
        }

        // Horas opcionales pero formato válido
        if (isset($data['horainicio']) && !empty($data['horainicio'])) {
            if (!$this->isValidTime($data['horainicio'])) {
                $this->errors[] = "El formato de hora de inicio es inválido. Use HH:MM o HH:MM:SS.";
            }
        }

        if (isset($data['horafin']) && !empty($data['horafin'])) {
            if (!$this->isValidTime($data['horafin'])) {
                $this->errors[] = "El formato de hora de fin es inválido. Use HH:MM o HH:MM:SS.";
            }
        }

        // Usuario asignado opcional (ID numérico)
        if (isset($data['usuarioasignado_id']) && !empty($data['usuarioasignado_id'])) {
            if (!is_numeric($data['usuarioasignado_id'])) {
                $this->errors[] = "El ID de usuario asignado debe ser numérico.";
            }
        }

        // Categoría opcional (ID numérico)
        if (isset($data['categoria_id']) && !empty($data['categoria_id'])) {
            if (!is_numeric($data['categoria_id'])) {
                $this->errors[] = "El ID de categoría debe ser numérico.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida los datos para actualizar una subtarea
     */
    public function validateUpdate(array $data): bool
    {
        $this->errors = [];

        // Al menos un campo debe estar presente
        $camposPermitidos = [
            'titulo', 'descripcion', 'estado', 'prioridad',
            'fechaAsignacion', 'fechaVencimiento', 'horainicio', 'horafin',
            'categoria_id', 'usuarioasignado_id', 'progreso'
        ];

        $hasValidField = false;
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $hasValidField = true;
                break;
            }
        }

        if (!$hasValidField) {
            $this->errors[] = "Debe proporcionar al menos un campo para actualizar.";
            return false;
        }

        // Título si está presente
        if (isset($data['titulo'])) {
            $titulo = trim($data['titulo']);
            if (empty($titulo)) {
                $this->errors[] = "El título no puede estar vacío.";
            } elseif (strlen($titulo) < 3) {
                $this->errors[] = "El título debe tener al menos 3 caracteres.";
            } elseif (strlen($titulo) > 250) {
                $this->errors[] = "El título no puede exceder 250 caracteres.";
            }
        }

        // Estado si está presente
        if (isset($data['estado']) && !empty($data['estado'])) {
            if (!TaskConfig::isValidSubtareaEstado($data['estado'])) {
                $validEstados = implode(', ', TaskConfig::SUBTAREA_ESTADOS);
                $this->errors[] = "El estado debe ser: {$validEstados}.";
            }
        }

        // Prioridad si está presente
        if (isset($data['prioridad']) && !empty($data['prioridad'])) {
            if (!TaskConfig::isValidSubtareaPrioridad($data['prioridad'])) {
                $validPrioridades = implode(', ', TaskConfig::SUBTAREA_PRIORIDADES);
                $this->errors[] = "La prioridad debe ser: {$validPrioridades}.";
            }
        }

        // Progreso si está presente (0-100)
        if (isset($data['progreso'])) {
            if (!is_numeric($data['progreso'])) {
                $this->errors[] = "El progreso debe ser numérico.";
            } elseif ($data['progreso'] < 0 || $data['progreso'] > 100) {
                $this->errors[] = "El progreso debe estar entre 0 y 100.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida el archivo de imagen para completar subtarea
     */
    public function validateCompletionImage(array $file): bool
    {
        $this->errors = [];

        // Verificar que existe el archivo
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = "La imagen de evidencia es requerida para completar la subtarea.";
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

        // Validar tamaño (usando TaskConfig)
        $fileSize = filesize($file['tmp_name']);
        if ($fileSize === false || !TaskConfig::isFileSizeValid($fileSize)) {
            $sizeMB = number_format(TaskConfig::MAX_FILE_SIZE_BYTES / 1048576, 1);
            $this->errors[] = "La imagen no puede exceder {$sizeMB} MB.";
        }

        // Validar tipo MIME real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$mimeType || !TaskConfig::isAllowedMimeType($mimeType)) {
            $allowedTypes = implode(', ', TaskConfig::ALLOWED_EXTENSIONS);
            $this->errors[] = "Solo se permiten imágenes: {$allowedTypes}.";
        }

        return empty($this->errors);
    }

    /**
     * Valida que una fecha tenga formato correcto (YYYY-MM-DD)
     */
    private function isValidDate(string $date): bool
    {
        if (strlen($date) !== 10) {
            return false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }

    /**
     * Valida que una hora tenga formato correcto (HH:MM o HH:MM:SS)
     */
    private function isValidTime(string $time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time) === 1;
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
            'mensajes' => ["Se obtuvieron {$count} subtarea(s)."]
        ];
    }

    /**
     * Respuesta de lista vacía
     */
    public function listEmpty(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["No hay subtareas disponibles."]
        ];
    }

    /**
     * Error al obtener lista
     */
    public function listError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al obtener la lista de subtareas."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE CREACIÓN
    // ============================================================

    /**
     * Subtarea creada exitosamente
     */
    public function createSuccess(string $titulo): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Subtarea '{$titulo}' creada exitosamente."]
        ];
    }

    /**
     * Error al crear subtarea
     */
    public function createError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al crear la subtarea."]
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
    // SECCIÓN: RESPUESTAS DE COMPLETAR SUBTAREA
    // ============================================================

    /**
     * Subtarea completada exitosamente
     */
    public function completeSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Subtarea completada exitosamente."]
        ];
    }

    /**
     * Error: subtarea no encontrada
     */
    public function subtareaNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Subtarea no encontrada."]
        ];
    }

    /**
     * Error: subtarea no asignada al usuario
     */
    public function notAssignedToYou(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Solo puedes completar subtareas que te están asignadas."]
        ];
    }

    /**
     * Error: subtarea ya completada
     */
    public function alreadyCompleted(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Esta subtarea ya fue completada."]
        ];
    }

    /**
     * Error: estado no permite completar
     */
    public function cannotComplete(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["El estado actual de la subtarea no permite completarla."]
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
     * Error al completar subtarea
     */
    public function completeError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al completar la subtarea."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ACTUALIZACIÓN
    // ============================================================

    /**
     * Subtarea actualizada exitosamente
     */
    public function updateSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Subtarea actualizada exitosamente."]
        ];
    }

    /**
     * Error al actualizar subtarea
     */
    public function updateError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al actualizar la subtarea."]
        ];
    }

    /**
     * Datos de actualización inválidos
     */
    public function updateValidationError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => $this->errors
        ];
    }

    /**
     * Estado actualizado exitosamente
     */
    public function statusUpdateSuccess(string $newEstado): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Estado de subtarea actualizado a '{$newEstado}'."]
        ];
    }

    /**
     * Error: estado inválido
     */
    public function invalidEstado(): array
    {
        $validEstados = implode(', ', TaskConfig::SUBTAREA_ESTADOS);
        return [
            'tipo' => 3,
            'mensajes' => ["Estado inválido. Los estados válidos son: {$validEstados}."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ELIMINACIÓN
    // ============================================================

    /**
     * Subtarea eliminada exitosamente
     */
    public function deleteSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Subtarea eliminada exitosamente."]
        ];
    }

    /**
     * Error al eliminar subtarea
     */
    public function deleteError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al eliminar la subtarea."]
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
     * Tarea padre no encontrada
     */
    public function taskNotFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["La tarea padre no existe."]
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

    /**
     * Valida que el estado sea válido
     */
    public function validateEstado(string $estado): bool
    {
        return TaskConfig::isValidSubtareaEstado($estado);
    }

    /**
     * Valida que la prioridad sea válida
     */
    public function validatePrioridad(string $prioridad): bool
    {
        return TaskConfig::isValidSubtareaPrioridad($prioridad);
    }
}
