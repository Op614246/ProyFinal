<?php
/**
 * TaskConfig.php
 * 
 * Configuración centralizada para el módulo de tareas.
 * Elimina hardcoding y facilita mantenimiento.
 */

class TaskConfig
{
    // ============================================================
    // ESTADOS DE TAREA
    // ============================================================
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROCESS = 'in_process';
    const STATUS_COMPLETED = 'completed';
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_CLOSED = 'closed';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROCESS,
        self::STATUS_COMPLETED,
        self::STATUS_INCOMPLETE,
        self::STATUS_INACTIVE,
        self::STATUS_CLOSED
    ];

    // Estados que permiten completar
    const COMPLETABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROCESS
    ];

    // Estados que permiten reabrir
    const REOPENABLE_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_INCOMPLETE,
        self::STATUS_INACTIVE
    ];

    // ============================================================
    // PRIORIDADES
    // ============================================================
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    const PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW
    ];

    // ============================================================
    // ROLES DE USUARIO
    // ============================================================
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    // ============================================================
    // CONFIGURACIÓN DE ARCHIVOS
    // ============================================================
    const MAX_FILE_SIZE_KB = 1536;  // 1.5 MB
    const MAX_FILE_SIZE_BYTES = 1572864;  // 1.5 MB en bytes

    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // ============================================================
    // TIPOS DE EVIDENCIA (para task_evidencias.tipo)
    // ============================================================
    const EVIDENCE_TYPE_IMAGE = 'imagen';
    const EVIDENCE_TYPE_DOCUMENT = 'documento';
    const EVIDENCE_TYPE_OTHER = 'otro';

    const EVIDENCE_TYPES = [
        self::EVIDENCE_TYPE_IMAGE,
        self::EVIDENCE_TYPE_DOCUMENT,
        self::EVIDENCE_TYPE_OTHER
    ];

    // ============================================================
    // RUTAS DE UPLOAD
    // ============================================================
    const UPLOAD_DIR = 'uploads/evidencias/';
    const UPLOAD_DIR_ABSOLUTE = __DIR__ . '/../../uploads/evidencias/';

    // ============================================================
    // CONFIGURACIÓN DE NEGOCIO
    // ============================================================
    const DEFAULT_DEADLINE_DAYS = 2;  // Días por defecto para deadline
    const AUTO_INACTIVE_DAYS = 1;     // Días después de fecha_asignacion para inactivar

    // ============================================================
    // MÉTODOS DE UTILIDAD
    // ============================================================

    /**
     * Valida si un estado es válido
     */
    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    /**
     * Valida si una prioridad es válida
     */
    public static function isValidPriority(string $priority): bool
    {
        return in_array($priority, self::PRIORITIES);
    }

    /**
     * Valida si un archivo tiene tipo MIME permitido
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * Valida si el tamaño del archivo está dentro del límite
     */
    public static function isFileSizeValid(int $sizeBytes): bool
    {
        return $sizeBytes <= self::MAX_FILE_SIZE_BYTES;
    }

    /**
     * Verifica si una tarea puede ser completada según su estado
     */
    public static function canComplete(string $status): bool
    {
        return in_array($status, self::COMPLETABLE_STATUSES);
    }

    /**
     * Verifica si una tarea puede ser reabierta según su estado
     */
    public static function canReopen(string $status): bool
    {
        return in_array($status, self::REOPENABLE_STATUSES);
    }

    /**
     * Verifica si el usuario es admin
     */
    public static function isAdmin(string $role): bool
    {
        return $role === self::ROLE_ADMIN;
    }

    /**
     * Genera nombre único para archivo de evidencia
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @param string $originalName Nombre original del archivo
     * @return string Nombre único generado
     */
    public static function generateEvidenceFileName(int $taskId, int $userId, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Si la extensión no está en las permitidas, usar jpg por defecto
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $extension = 'jpg';
        }
        
        return 'tarea_' . $taskId . '_u' . $userId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    }

    /**
     * Calcula deadline por defecto
     */
    public static function getDefaultDeadline(?string $fromDate = null): string
    {
        $base = $fromDate ?? date('Y-m-d');
        return date('Y-m-d', strtotime($base . ' +' . self::DEFAULT_DEADLINE_DAYS . ' days'));
    }
}
