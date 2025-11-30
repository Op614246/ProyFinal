<?php
/**
 * TaskRepository.php
 * 
 * Repositorio para operaciones de tareas (Schema Imagen 2)
 * Soporta funcionalidades de Admin y User con roles diferenciados
 * 
 * Estados: pending, in_process, completed, incomplete, inactive
 * Prioridades: high, medium, low
 */

class TaskRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    // ============================================================
    // SECCIÓN: CONSULTAS DE LECTURA
    // ============================================================

    /**
     * Obtiene todas las tareas con filtros para Admin
     * 
     * @param array $filters Filtros: fecha, fecha_inicio, fecha_fin, status, priority, assigned_user_id, sucursal
     * @return array Lista de tareas
     */
    public function getAllForAdmin(array $filters = []): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    c.color AS categoria_color,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.fecha_asignacion,
                    t.horainicio,
                    t.horafin,
                    t.assigned_user_id,
                    CONCAT(ua.nombre, ' ', ua.apellido) AS assigned_username,
                    t.created_by_user_id,
                    CONCAT(uc.nombre, ' ', uc.apellido) AS created_by_name,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre,
                    t.progreso,
                    t.completed_at,
                    t.evidence_image,
                    t.completion_notes,
                    t.motivo_reapertura,
                    t.observaciones_reapertura,
                    t.created_at,
                    t.updated_at,
                    (SELECT COUNT(*) FROM task_evidence te WHERE te.task_id = t.id) AS evidencia_count
                FROM tasks t
                LEFT JOIN users ua ON t.assigned_user_id = ua.id
                LEFT JOIN users uc ON t.created_by_user_id = uc.id
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE 1=1";

        $params = [];

        // Filtro por fecha específica
        if (!empty($filters['fecha'])) {
            $sql .= " AND DATE(t.fecha_asignacion) = :fecha";
            $params[':fecha'] = $filters['fecha'];
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND DATE(t.fecha_asignacion) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }

        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND DATE(t.fecha_asignacion) <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }

        // Filtro por estado
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtro por prioridad
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        // Filtro por usuario asignado
        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND t.assigned_user_id = :assigned_user_id";
            $params[':assigned_user_id'] = $filters['assigned_user_id'];
        }

        // Filtro por sucursal
        if (!empty($filters['sucursal_id'])) {
            $sql .= " AND t.sucursal_id = :sucursal_id";
            $params[':sucursal_id'] = $filters['sucursal_id'];
        }

        // Filtro por categoría
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND t.categoria_id = :categoria_id";
            $params[':categoria_id'] = $filters['categoria_id'];
        }

        $sql .= " ORDER BY t.fecha_asignacion DESC, t.horainicio ASC, t.priority DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene tareas para un usuario (rol user)
     * Ordenamiento: Prioridad > Estado > Deadline
     * 
     * @param int $userId ID del usuario
     * @param array $filters Filtros opcionales
     * @return array Lista de tareas
     */
    public function getAllForUser(int $userId, array $filters = []): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    c.color AS categoria_color,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.fecha_asignacion,
                    t.horainicio,
                    t.horafin,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre,
                    t.progreso,
                    t.completed_at,
                    t.evidence_image,
                    t.completion_notes,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE t.assigned_user_id = :user_id";

        $params = [':user_id' => $userId];

        // Filtro por fecha específica (mismo día)
        if (!empty($filters['fecha'])) {
            $sql .= " AND DATE(t.fecha_asignacion) = :fecha";
            $params[':fecha'] = $filters['fecha'];
        }

        // Filtro por estado
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY 
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    CASE 
                        WHEN t.status = 'completed' THEN 2
                        WHEN t.status = 'inactive' THEN 3
                        ELSE 1
                    END,
                    CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END,
                    t.deadline ASC,
                    t.horainicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene tareas disponibles para auto-asignación (solo del día actual)
     * Solo tareas sin asignar y del mismo día
     * 
     * @return array Lista de tareas disponibles
     */
    public function getAvailableTasksForToday(): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    t.priority,
                    t.deadline,
                    t.fecha_asignacion,
                    t.horainicio,
                    t.horafin,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE t.assigned_user_id IS NULL
                  AND t.status IN ('pending')
                  AND DATE(t.fecha_asignacion) = CURDATE()
                ORDER BY 
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    t.horainicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una tarea por ID con información completa
     * 
     * @param int $taskId ID de la tarea
     * @return array|false Datos de la tarea o false si no existe
     */
    public function getById(int $taskId)
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    c.color AS categoria_color,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.fecha_asignacion,
                    t.horainicio,
                    t.horafin,
                    t.assigned_user_id,
                    ua.username AS assigned_username,
                    CONCAT(ua.nombre, ' ', ua.apellido) AS assigned_fullname,
                    t.created_by_user_id,
                    CONCAT(uc.nombre, ' ', uc.apellido) AS created_by_name,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre,
                    t.progreso,
                    t.completed_at,
                    t.evidence_image,
                    t.completion_notes,
                    t.motivo_reapertura,
                    t.observaciones_reapertura,
                    CONCAT(ur.nombre, ' ', ur.apellido) AS reabierta_por_name,
                    t.fecha_reapertura,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN users ua ON t.assigned_user_id = ua.id
                LEFT JOIN users uc ON t.created_by_user_id = uc.id
                LEFT JOIN users ur ON t.reabierta_por = ur.id
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE t.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            // Obtener evidencias
            $task['evidencias'] = $this->getEvidenceByTaskId($taskId);
        }

        return $task;
    }

    /**
     * Obtiene estadísticas de tareas para dashboard
     * 
     * @param int|null $userId ID del usuario (null para admin)
     * @return array Estadísticas
     */
    public function getStatistics(?int $userId = null): array
    {
        $where = $userId ? "WHERE t.assigned_user_id = :user_id" : "";
        $params = $userId ? [':user_id' => $userId] : [];

        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN status = 'in_process' THEN 1 ELSE 0 END) as en_proceso,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incompletas,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactivas,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as alta_prioridad,
                    SUM(CASE WHEN deadline < CURDATE() AND status NOT IN ('completed', 'inactive') THEN 1 ELSE 0 END) as vencidas
                FROM tasks t
                $where";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // SECCIÓN: OPERACIONES DE ESCRITURA - ADMIN
    // ============================================================

    /**
     * Crea una nueva tarea (Admin only)
     * 
     * @param array $data Datos de la tarea
     * @param int $createdByUserId ID del usuario que crea
     * @return int|false ID de la tarea creada o false si falla
     */
    public function create(array $data, int $createdByUserId)
    {
        $sql = "INSERT INTO tasks (
                    title, description, categoria_id, status, priority,
                    deadline, fecha_asignacion, horainicio, horafin,
                    assigned_user_id, created_by_user_id, sucursal_id,
                    created_at, updated_at
                ) VALUES (
                    :title, :description, :categoria_id, :status, :priority,
                    :deadline, :fecha_asignacion, :horainicio, :horafin,
                    :assigned_user_id, :created_by_user_id, :sucursal_id,
                    NOW(), NOW()
                )";

        // Calcular deadline si no se proporciona (2 días desde fecha_asignacion)
        $fechaAsignacion = $data['fecha_asignacion'] ?? date('Y-m-d');
        $deadline = $data['deadline'] ?? date('Y-m-d', strtotime($fechaAsignacion . ' +2 days'));

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':priority' => $data['priority'] ?? 'medium',
            ':deadline' => $deadline,
            ':fecha_asignacion' => $fechaAsignacion,
            ':horainicio' => $data['horainicio'] ?? null,
            ':horafin' => $data['horafin'] ?? null,
            ':assigned_user_id' => $data['assigned_user_id'] ?? null,
            ':created_by_user_id' => $createdByUserId,
            ':sucursal_id' => $data['sucursal_id'] ?? null
        ]);

        if ($result) {
            $taskId = (int)$this->db->lastInsertId();
            $this->logHistory($taskId, $createdByUserId, 'created', null, json_encode($data));
            return $taskId;
        }

        return false;
    }

    /**
     * Actualiza una tarea existente (Admin only)
     * 
     * @param int $taskId ID de la tarea
     * @param array $data Datos a actualizar
     * @param int $userId ID del usuario que actualiza
     * @return bool
     */
    public function update(int $taskId, array $data, int $userId): bool
    {
        $oldData = $this->getById($taskId);
        
        $fields = [];
        $params = [':task_id' => $taskId];

        $allowedFields = [
            'title', 'description', 'categoria_id', 'status', 'priority',
            'deadline', 'fecha_asignacion', 'horainicio', 'horafin',
            'assigned_user_id', 'sucursal_id', 'progreso'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            $this->logHistory($taskId, $userId, 'updated', json_encode($oldData), json_encode($data));
        }

        return $result;
    }

    /**
     * Asigna una tarea a un usuario (Admin o auto-asignación)
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario a asignar
     * @param int $assignedBy ID del usuario que asigna
     * @return bool
     */
    public function assign(int $taskId, int $userId, int $assignedBy): bool
    {
        $sql = "UPDATE tasks 
                SET assigned_user_id = :user_id,
                    status = CASE 
                        WHEN status = 'pending' THEN 'in_process'
                        ELSE status 
                    END,
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':task_id' => $taskId
        ]);

        if ($result) {
            $this->logHistory($taskId, $assignedBy, 'assigned', null, "Asignado a usuario ID: $userId");
            $this->updateUserTaskCount($userId, 1);
        }

        return $result;
    }

    /**
     * Reabre una tarea completada o incompleta (Admin only)
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del admin que reabre
     * @param string $motivo Motivo de reapertura
     * @param string|null $observaciones Observaciones adicionales
     * @return bool
     */
    public function reopen(int $taskId, int $userId, string $motivo, ?string $observaciones = null): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'pending',
                    motivo_reapertura = :motivo,
                    observaciones_reapertura = :observaciones,
                    reabierta_por = :user_id,
                    fecha_reapertura = NOW(),
                    completed_at = NULL,
                    evidence_image = NULL,
                    completion_notes = NULL,
                    progreso = 0,
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':motivo' => $motivo,
            ':observaciones' => $observaciones,
            ':user_id' => $userId,
            ':task_id' => $taskId
        ]);

        if ($result) {
            $this->logHistory($taskId, $userId, 'reopened', null, "Motivo: $motivo");
        }

        return $result;
    }

    /**
     * Elimina una tarea (Admin only - soft delete cambiando a inactive)
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario que elimina
     * @return bool
     */
    public function delete(int $taskId, int $userId): bool
    {
        $sql = "UPDATE tasks SET status = 'inactive', updated_at = NOW() WHERE id = :task_id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':task_id' => $taskId]);

        if ($result) {
            $this->logHistory($taskId, $userId, 'deleted', null, 'Tarea marcada como inactiva');
        }

        return $result;
    }

    // ============================================================
    // SECCIÓN: OPERACIONES DE ESCRITURA - USER
    // ============================================================

    /**
     * Usuario se auto-asigna una tarea disponible del día actual
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @return bool
     */
    public function selfAssign(int $taskId, int $userId): bool
    {
        // Verificar que la tarea esté disponible y sea del día actual
        $sql = "SELECT id FROM tasks 
                WHERE id = :task_id 
                  AND assigned_user_id IS NULL 
                  AND status = 'pending'
                  AND DATE(fecha_asignacion) = CURDATE()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);

        if (!$stmt->fetch()) {
            return false; // Tarea no disponible o no es del día actual
        }

        return $this->assign($taskId, $userId, $userId);
    }

    /**
     * Completa una tarea con observaciones e imagen de evidencia
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @param string $observaciones Observaciones del completado
     * @param string|null $evidencePath Ruta de la imagen de evidencia
     * @return bool
     */
    public function complete(int $taskId, int $userId, string $observaciones, ?string $evidencePath = null): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'completed',
                    completion_notes = :observaciones,
                    evidence_image = :evidence_path,
                    progreso = 100,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :task_id
                  AND assigned_user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':observaciones' => $observaciones,
            ':evidence_path' => $evidencePath,
            ':task_id' => $taskId,
            ':user_id' => $userId
        ]);

        if ($result && $stmt->rowCount() > 0) {
            $this->logHistory($taskId, $userId, 'completed', null, $observaciones);
            $this->updateUserTaskCount($userId, -1);
            return true;
        }

        return false;
    }

    /**
     * Actualiza el progreso de una tarea
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @param int $progreso Porcentaje de progreso (0-100)
     * @return bool
     */
    public function updateProgress(int $taskId, int $userId, int $progreso): bool
    {
        $progreso = max(0, min(100, $progreso));

        $sql = "UPDATE tasks 
                SET progreso = :progreso,
                    status = CASE 
                        WHEN :progreso2 = 100 THEN 'completed'
                        WHEN :progreso3 > 0 THEN 'in_process'
                        ELSE status
                    END,
                    completed_at = CASE WHEN :progreso4 = 100 THEN NOW() ELSE completed_at END,
                    updated_at = NOW()
                WHERE id = :task_id
                  AND assigned_user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':progreso' => $progreso,
            ':progreso2' => $progreso,
            ':progreso3' => $progreso,
            ':progreso4' => $progreso,
            ':task_id' => $taskId,
            ':user_id' => $userId
        ]);
    }

    // ============================================================
    // SECCIÓN: EVIDENCIAS
    // ============================================================

    /**
     * Agrega evidencia (imagen) a una tarea
     * Valida tamaño máximo de 1.5MB
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @param string $filePath Ruta del archivo
     * @param string $fileName Nombre del archivo
     * @param int $fileSizeKb Tamaño en KB
     * @param string $mimeType Tipo MIME
     * @param string|null $observaciones Observaciones
     * @return int|false ID de la evidencia o false si falla
     */
    public function addEvidence(
        int $taskId, 
        int $userId, 
        string $filePath, 
        string $fileName, 
        int $fileSizeKb, 
        string $mimeType,
        ?string $observaciones = null
    ) {
        // Validar tamaño máximo (1.5 MB = 1536 KB)
        if ($fileSizeKb > 1536) {
            return false;
        }

        // Validar tipos permitidos
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            return false;
        }

        $sql = "INSERT INTO task_evidence (
                    task_id, user_id, file_path, file_name, 
                    file_size_kb, mime_type, observaciones, uploaded_at
                ) VALUES (
                    :task_id, :user_id, :file_path, :file_name,
                    :file_size_kb, :mime_type, :observaciones, NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
            ':file_path' => $filePath,
            ':file_name' => $fileName,
            ':file_size_kb' => $fileSizeKb,
            ':mime_type' => $mimeType,
            ':observaciones' => $observaciones
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Obtiene las evidencias de una tarea
     * 
     * @param int $taskId ID de la tarea
     * @return array Lista de evidencias
     */
    public function getEvidenceByTaskId(int $taskId): array
    {
        $sql = "SELECT 
                    te.id,
                    te.file_path,
                    te.file_name,
                    te.file_size_kb,
                    te.mime_type,
                    te.observaciones,
                    te.uploaded_at,
                    u.username,
                    CONCAT(u.nombre, ' ', u.apellido) AS uploaded_by
                FROM task_evidence te
                LEFT JOIN users u ON te.user_id = u.id
                WHERE te.task_id = :task_id
                ORDER BY te.uploaded_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // SECCIÓN: VERIFICACIONES Y UTILIDADES
    // ============================================================

    /**
     * Verifica si una tarea existe
     */
    public function exists(int $taskId): bool
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si una tarea está asignada a un usuario específico
     */
    public function isAssignedTo(int $taskId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM tasks 
                WHERE id = :task_id AND assigned_user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si una tarea está disponible para asignar
     */
    public function isAvailable(int $taskId): bool
    {
        $sql = "SELECT assigned_user_id FROM tasks WHERE id = :id AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['assigned_user_id'] === null;
    }

    /**
     * Obtiene tareas disponibles (sin asignar) para usuarios
     */
    public function getAvailableTasks(): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    t.priority,
                    t.deadline,
                    t.fecha_asignacion,
                    t.horainicio,
                    t.horafin,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE t.assigned_user_id IS NULL
                  AND t.status IN ('pending')
                ORDER BY 
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    t.fecha_asignacion ASC,
                    t.horainicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si un usuario existe
     */
    public function userExists(int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene el nombre de usuario por ID
     */
    public function getUsernameById(int $userId): ?string
    {
        $sql = "SELECT CONCAT(nombre, ' ', apellido) as fullname FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['fullname'] : null;
    }

    /**
     * Obtiene usuarios disponibles para asignación
     */
    public function getAvailableUsers(): array
    {
        $sql = "SELECT 
                    u.id, 
                    u.username, 
                    CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS nombre_completo,
                    COALESCE(u.departamento, '') AS departamento,
                    COALESCE(u.estado, 'Disponible') AS estado,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_user_id = u.id AND t.status IN ('pending', 'in_process')) AS tareas_activas
                FROM users u
                WHERE u.role = 'user' 
                  AND u.is_active = 1
                ORDER BY u.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra historial de cambios
     */
    private function logHistory(int $taskId, int $userId, string $action, ?string $previousValue, ?string $newValue): void
    {
        try {
            $sql = "INSERT INTO task_history (task_id, user_id, action, previous_value, new_value, created_at)
                    VALUES (:task_id, :user_id, :action, :previous_value, :new_value, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':task_id' => $taskId,
                ':user_id' => $userId,
                ':action' => $action,
                ':previous_value' => $previousValue,
                ':new_value' => $newValue
            ]);
        } catch (Exception $e) {
            // Log silently fails - no interrumpir la operación principal
            Logger::warning('Failed to log task history: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza contador de tareas activas del usuario
     */
    private function updateUserTaskCount(int $userId, int $increment): void
    {
        try {
            $sql = "UPDATE users SET tareas_activas = GREATEST(0, tareas_activas + :increment) WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':increment' => $increment, ':user_id' => $userId]);
        } catch (Exception $e) {
            Logger::warning('Failed to update user task count: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el estado de una tarea
     * 
     * @param int $taskId ID de la tarea
     * @param string $status Nuevo estado
     * @return bool
     */
    public function updateStatus(int $taskId, string $status): bool
    {
        $sql = "UPDATE tasks 
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Marca tareas vencidas como incompletas (2 días después del deadline)
     */
    public function markOverdueTasks(): int
    {
        $sql = "UPDATE tasks
                SET status = 'incomplete',
                    updated_at = NOW()
                WHERE status IN ('pending', 'in_process')
                  AND deadline IS NOT NULL
                  AND deadline < DATE_SUB(CURDATE(), INTERVAL 2 DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
