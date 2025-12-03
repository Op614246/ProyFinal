<?php
/**
 * CategoriaValidator.php
 * 
 * Validador y generador de respuestas para el módulo de categorías.
 */

class CategoriaValidator
{
    private $errors = [];

    // ============================================================
    // SECCIÓN: VALIDACIONES DE ENTRADA
    // ============================================================

    /**
     * Valida los datos para crear una categoría
     */
    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        // Nombre requerido y validado
        if (!isset($data['nombre']) || empty(trim($data['nombre']))) {
            $this->errors[] = "El nombre de la categoría es requerido.";
        } else {
            $nombre = trim($data['nombre']);
            if (strlen($nombre) < 2) {
                $this->errors[] = "El nombre debe tener al menos 2 caracteres.";
            }
            if (strlen($nombre) > 100) {
                $this->errors[] = "El nombre no puede exceder 100 caracteres.";
            }
        }

        // Descripción opcional pero con límite
        if (isset($data['descripcion']) && !empty($data['descripcion'])) {
            $descripcion = is_string($data['descripcion']) ? $data['descripcion'] : '';
            if (strlen($descripcion) > 500) {
                $this->errors[] = "La descripción no puede exceder 500 caracteres.";
            }
        }

        // Color opcional pero formato válido (hex)
        if (isset($data['color']) && !empty($data['color'])) {
            if (!$this->isValidHexColor($data['color'])) {
                $this->errors[] = "El formato de color es inválido. Use formato hexadecimal (#RRGGBB).";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida los datos para actualizar una categoría
     */
    public function validateUpdate(array $data): bool
    {
        $this->errors = [];

        // Al menos un campo debe estar presente
        $camposPermitidos = ['nombre', 'descripcion', 'color', 'activo'];

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

        // Nombre si está presente
        if (isset($data['nombre'])) {
            $nombre = trim($data['nombre']);
            if (empty($nombre)) {
                $this->errors[] = "El nombre no puede estar vacío.";
            } elseif (strlen($nombre) < 2) {
                $this->errors[] = "El nombre debe tener al menos 2 caracteres.";
            } elseif (strlen($nombre) > 100) {
                $this->errors[] = "El nombre no puede exceder 100 caracteres.";
            }
        }

        // Descripción si está presente
        if (isset($data['descripcion']) && !empty($data['descripcion'])) {
            $descripcion = is_string($data['descripcion']) ? $data['descripcion'] : '';
            if (strlen($descripcion) > 500) {
                $this->errors[] = "La descripción no puede exceder 500 caracteres.";
            }
        }

        // Color si está presente
        if (isset($data['color']) && !empty($data['color'])) {
            if (!$this->isValidHexColor($data['color'])) {
                $this->errors[] = "El formato de color es inválido. Use formato hexadecimal (#RRGGBB).";
            }
        }

        // Activo si está presente (boolean)
        if (isset($data['activo'])) {
            if (!is_bool($data['activo']) && !in_array($data['activo'], [0, 1, '0', '1'], true)) {
                $this->errors[] = "El campo activo debe ser verdadero o falso.";
            }
        }

        return empty($this->errors);
    }

    /**
     * Valida formato de color hexadecimal
     */
    private function isValidHexColor(string $color): bool
    {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
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
            'mensajes' => ["Se obtuvieron {$count} categoría(s)."]
        ];
    }

    /**
     * Respuesta de lista vacía
     */
    public function listEmpty(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["No hay categorías disponibles."]
        ];
    }

    /**
     * Error al obtener lista
     */
    public function listError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al obtener la lista de categorías."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE CREACIÓN
    // ============================================================

    /**
     * Categoría creada exitosamente
     */
    public function createSuccess(string $nombre): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Categoría '{$nombre}' creada exitosamente."]
        ];
    }

    /**
     * Error al crear categoría
     */
    public function createError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al crear la categoría."]
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

    /**
     * Error: nombre duplicado
     */
    public function duplicateName(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Ya existe una categoría con ese nombre."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE OBTENCIÓN
    // ============================================================

    /**
     * Categoría obtenida exitosamente
     */
    public function getSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Categoría obtenida correctamente."]
        ];
    }

    /**
     * Error: categoría no encontrada
     */
    public function notFound(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Categoría no encontrada."]
        ];
    }

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ACTUALIZACIÓN
    // ============================================================

    /**
     * Categoría actualizada exitosamente
     */
    public function updateSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Categoría actualizada exitosamente."]
        ];
    }

    /**
     * Error al actualizar categoría
     */
    public function updateError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al actualizar la categoría."]
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

    // ============================================================
    // SECCIÓN: RESPUESTAS DE ELIMINACIÓN
    // ============================================================

    /**
     * Categoría eliminada exitosamente
     */
    public function deleteSuccess(): array
    {
        return [
            'tipo' => 1,
            'mensajes' => ["Categoría desactivada exitosamente."]
        ];
    }

    /**
     * Error al eliminar categoría
     */
    public function deleteError(): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["Error al desactivar la categoría."]
        ];
    }

    /**
     * No se puede eliminar categoría con tareas asociadas
     */
    public function hasAssociatedTasks(int $count): array
    {
        return [
            'tipo' => 3,
            'mensajes' => ["No se puede eliminar la categoría porque tiene {$count} tarea(s) asociada(s)."]
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
