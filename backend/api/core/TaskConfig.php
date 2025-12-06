<?php
class TaskConfig
{
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

    const COMPLETABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROCESS
    ];

    const REOPENABLE_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_INCOMPLETE,
        self::STATUS_INACTIVE
    ];

    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    const PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW
    ];

    const STATUS_MAP = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_IN_PROCESS => 'En progreso',
        self::STATUS_COMPLETED => 'Completada',
        self::STATUS_INCOMPLETE => 'Incompleta',
        self::STATUS_INACTIVE => 'Inactiva',
        self::STATUS_CLOSED => 'Cerrada'
    ];

    const PRIORITY_MAP = [
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_MEDIUM => 'Media',
        self::PRIORITY_LOW => 'Baja'
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    const MAX_FILE_SIZE_KB = 1536;
    const MAX_FILE_SIZE_BYTES = 1572864;

    const ALLOWED_MIME_TYPES = [
        'image/jpg',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    const EVIDENCE_TYPE_IMAGE = 'imagen';
    const EVIDENCE_TYPE_DOCUMENT = 'documento';
    const EVIDENCE_TYPE_OTHER = 'otro';

    const EVIDENCE_TYPES = [
        self::EVIDENCE_TYPE_IMAGE,
        self::EVIDENCE_TYPE_DOCUMENT,
        self::EVIDENCE_TYPE_OTHER
    ];

    const UPLOAD_DIR = __DIR__ . '/../uploads/evidencias/';
    const UPLOAD_PATH_RELATIVE = 'api/uploads/evidencias/';

    const DEFAULT_DEADLINE_DAYS = 2;
    const AUTO_INACTIVE_DAYS = 1;

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    public static function isValidPriority(string $priority): bool
    {
        return in_array($priority, self::PRIORITIES);
    }

    public static function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    public static function isFileSizeValid(int $sizeBytes): bool
    {
        return $sizeBytes <= self::MAX_FILE_SIZE_BYTES;
    }

    public static function canComplete(string $status): bool
    {
        return in_array($status, self::COMPLETABLE_STATUSES);
    }

    public static function canReopen(string $status): bool
    {
        return in_array($status, self::REOPENABLE_STATUSES);
    }

    public static function isAdmin(string $role): bool
    {
        return $role === self::ROLE_ADMIN;
    }

    public static function generateEvidenceFileName(int $taskId, int $userId, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $extension = 'jpg';
        }
        
        return 'tarea_' . $taskId . '_u' . $userId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    }

    public static function getDefaultDeadline(?string $fromDate = null): string
    {
        $base = $fromDate ?? date('Y-m-d');
        return date('Y-m-d', strtotime($base . ' +' . self::DEFAULT_DEADLINE_DAYS . ' days'));
    }
}
