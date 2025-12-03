<?php
/**
 * TaskRepository.php
 * 
 * Repositorio único para operaciones de tareas.
 * - Usa TaskConfig para constantes (sin hardcoding)
 * - Métodos unificados para Admin y User
 * - Clean Code: métodos pequeños y reutilizables
 */

require_once __DIR__ . '/../core/TaskConfig.php';

class TaskRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    // ============================================================
    // CONSULTAS DE LECTURA
    // ============================================================

    /**
     * Base SQL para consultas de tareas (evita duplicación)
     */
    private function getBaseTaskQuery(): string
    {
        return "SELECT 
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
                    CONCAT(ua.nombre, ' ', ua.apellido) AS assigned_user_name,
                    t.created_by_user_id,
                    CONCAT(uc.nombre, ' ', uc.apellido) AS created_by_name,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre,
                    t.progreso,
                    t.completed_at,
                    t.reopened_at,
                    t.created_at,
                    t.updated_at,
                    (SELECT COUNT(*) FROM task_evidencias te WHERE te.task_id = t.id) AS evidencia_count,
                    (SELECT COUNT(*) FROM subtareas st WHERE st.task_id = t.id) AS subtareas_total,
                    (SELECT COUNT(*) FROM subtareas st WHERE st.task_id = t.id AND (st.completada = 1 OR st.estado = 'Completada')) AS subtareas_completadas
                FROM tasks t
                LEFT JOIN users ua ON t.assigned_user_id = ua.id
                LEFT JOIN users uc ON t.created_by_user_id = uc.id
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id";
    }

    /**
     * Obtiene tareas con filtros dinámicos.
     * Método unificado para Admin y User.
     * 
     * @param array $filters Filtros opcionales
     * @return array Lista de tareas
     */
    public function getAll(array $filters = []): array
    {
        $sql = $this->getBaseTaskQuery() . " WHERE 1=1";
        $params = [];

        // Aplicar filtros dinámicamente
        $sql = $this->applyFilters($sql, $params, $filters);

        // Ordenamiento inteligente
        $sql .= " ORDER BY 
                    FIELD(t.status, '" . TaskConfig::STATUS_IN_PROCESS . "', '" . TaskConfig::STATUS_PENDING . "', '" . TaskConfig::STATUS_INCOMPLETE . "', '" . TaskConfig::STATUS_INACTIVE . "', '" . TaskConfig::STATUS_COMPLETED . "'),
                    FIELD(t.priority, '" . TaskConfig::PRIORITY_HIGH . "', '" . TaskConfig::PRIORITY_MEDIUM . "', '" . TaskConfig::PRIORITY_LOW . "'),
                    t.fecha_asignacion DESC,
                    t.horainicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aplica filtros dinámicos a una consulta SQL.
     */
    private function applyFilters(string $sql, array &$params, array $filters): string
    {
        // Fecha específica
        if (!empty($filters['fecha'])) {
            $sql .= " AND DATE(t.fecha_asignacion) = :fecha";
            $params[':fecha'] = $filters['fecha'];
        }

        // Rango de fechas
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND DATE(t.fecha_asignacion) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND DATE(t.fecha_asignacion) <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }

        // Estado
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Prioridad
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        // Usuario asignado
        if (!empty($filters['assigned_user_id'])) {
            $sql .= " AND t.assigned_user_id = :assigned_user_id";
            $params[':assigned_user_id'] = $filters['assigned_user_id'];
        }

        // Sucursal
        if (!empty($filters['sucursal_id'])) {
            $sql .= " AND t.sucursal_id = :sucursal_id";
            $params[':sucursal_id'] = $filters['sucursal_id'];
        }

        // Categoría
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND t.categoria_id = :categoria_id";
            $params[':categoria_id'] = $filters['categoria_id'];
        }

        // Sin asignar
        if (!empty($filters['sin_asignar'])) {
            $sql .= " AND t.assigned_user_id IS NULL";
        }

        return $sql;
    }

    /**
     * Alias para compatibilidad - Admin
     */
    public function getAllForAdmin(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    /**
     * Obtiene tareas de un usuario específico.
     */
    public function getByUser(int $userId, array $filters = []): array
    {
        $filters['assigned_user_id'] = $userId;
        return $this->getAll($filters);
    }

    /**
     * Alias para compatibilidad - User
     */
    public function getAllForUser(int $userId, array $filters = []): array
    {
        return $this->getByUser($userId, $filters);
    }

    /**
     * Obtiene tareas disponibles para auto-asignación (día actual, sin asignar).
     */
    public function getAvailableTasksForToday(): array
    {
        return $this->getAll([
            'fecha' => date('Y-m-d'),
            'sin_asignar' => true,
            'status' => TaskConfig::STATUS_PENDING
        ]);
    }

    /**
     * Obtiene tareas disponibles sin filtro de fecha.
     */
    public function getAvailableTasks(): array
    {
        return $this->getAll([
            'sin_asignar' => true,
            'status' => TaskConfig::STATUS_PENDING
        ]);
    }

    /**
     * Obtiene tareas disponibles con filtros aplicados.
     */
    public function getAvailableTasksFiltered(array $filters = []): array
    {
        // Forzar que sean sin asignar y pendientes
        $filters['sin_asignar'] = true;
        $filters['status'] = TaskConfig::STATUS_PENDING;
        
        return $this->getAll($filters);
    }

    /**
     * Obtiene una tarea por ID con información completa.
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
                    t.reopened_at,
                    t.reabierta_por,
                    CONCAT(ur.nombre, ' ', ur.apellido) AS reabierta_por_name,
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
            
            // Obtener última reapertura si existe
            $lastReopen = $this->getLastReopenInfo($taskId);
            if ($lastReopen) {
                $task['motivo_reapertura'] = $lastReopen['motivo'];
                $task['observaciones_reapertura'] = $lastReopen['observaciones'];
                $task['fecha_reapertura'] = $lastReopen['reopened_at'];
            }
        }

        return $task;
    }
    
    /**
     * Obtiene la última información de reapertura de una tarea
     */
    private function getLastReopenInfo(int $taskId): ?array
    {
        $sql = "SELECT motivo, observaciones, reopened_at 
                FROM task_reaperturas 
                WHERE task_id = :task_id 
                ORDER BY reopened_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
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

        $pending = TaskConfig::STATUS_PENDING;
        $inProcess = TaskConfig::STATUS_IN_PROCESS;
        $completed = TaskConfig::STATUS_COMPLETED;
        $incomplete = TaskConfig::STATUS_INCOMPLETE;
        $inactive = TaskConfig::STATUS_INACTIVE;
        $highPriority = TaskConfig::PRIORITY_HIGH;

        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = '$pending' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN status = '$inProcess' THEN 1 ELSE 0 END) as en_proceso,
                    SUM(CASE WHEN status = '$completed' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN status = '$incomplete' THEN 1 ELSE 0 END) as incompletas,
                    SUM(CASE WHEN status = '$inactive' THEN 1 ELSE 0 END) as inactivas,
                    SUM(CASE WHEN priority = '$highPriority' THEN 1 ELSE 0 END) as alta_prioridad,
                    SUM(CASE WHEN deadline < CURDATE() AND status NOT IN ('$completed', '$inactive') THEN 1 ELSE 0 END) as vencidas
                FROM tasks t
                $where";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // OPERACIONES DE ESCRITURA
    // ============================================================

    /**
     * Crea una nueva tarea.
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

        $fechaAsignacion = $data['fecha_asignacion'] ?? date('Y-m-d');
        $deadline = $data['deadline'] ?? TaskConfig::getDefaultDeadline($fechaAsignacion);

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':status' => $data['status'] ?? TaskConfig::STATUS_PENDING,
            ':priority' => $data['priority'] ?? TaskConfig::PRIORITY_MEDIUM,
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
            Logger::info('Tarea creada', ['task_id' => $taskId, 'created_by' => $createdByUserId]);
            return $taskId;
        }

        return false;
    }

    /**
     * Actualiza una tarea existente.
     */
    public function update(int $taskId, array $data, ?int $userId = null): bool
    {
        $allowedFields = [
            'title', 'description', 'categoria_id', 'status', 'priority',
            'deadline', 'fecha_asignacion', 'horainicio', 'horafin',
            'assigned_user_id', 'sucursal_id', 'progreso'
        ];

        $fields = [];
        $params = [':task_id' => $taskId];

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
            Logger::info('Tarea actualizada', ['task_id' => $taskId, 'fields' => array_keys($data)]);
        }

        return $result;
    }

    /**
     * Asigna una tarea a un usuario.
     */
    public function assign(int $taskId, int $userId, ?int $assignedBy = null): bool
    {
        $sql = "UPDATE tasks 
                SET assigned_user_id = :user_id,
                    status = CASE 
                        WHEN status = :pending THEN :in_process
                        ELSE status 
                    END,
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':pending' => TaskConfig::STATUS_PENDING,
            ':in_process' => TaskConfig::STATUS_IN_PROCESS,
            ':task_id' => $taskId
        ]);

        if ($result) {
            Logger::info('Tarea asignada', [
                'task_id' => $taskId,
                'assigned_to' => $userId,
                'assigned_by' => $assignedBy ?? $userId
            ]);
        }

        return $result;
    }

    /**
     * Reabre una tarea completada o incompleta.
     * Guarda historial en task_reaperturas.
     */
    public function reopen(int $taskId, int $userId, string $motivo, ?string $observaciones = null, array $newData = []): bool
    {
        // Obtener datos actuales para historial
        $current = $this->getById($taskId);
        if (!$current) {
            return false;
        }

        // Guardar historial de reapertura
        $this->saveReopenHistory($taskId, $userId, $motivo, $observaciones, $current, $newData);

        // Actualizar tarea
        $sql = "UPDATE tasks 
                SET status = :pending,
                    reopened_at = NOW(),
                    reabierta_por = :user_id,
                    completed_at = NULL,
                    progreso = 0,
                    updated_at = NOW()";

        $params = [
            ':pending' => TaskConfig::STATUS_PENDING,
            ':user_id' => $userId,
            ':task_id' => $taskId
        ];

        // Aplicar nuevos datos si se proporcionan
        if (!empty($newData['assigned_user_id'])) {
            $sql .= ", assigned_user_id = :new_assigned";
            $params[':new_assigned'] = $newData['assigned_user_id'];
        }
        if (!empty($newData['deadline'])) {
            $sql .= ", deadline = :new_deadline";
            $params[':new_deadline'] = $newData['deadline'];
        }
        if (!empty($newData['priority'])) {
            $sql .= ", priority = :new_priority";
            $params[':new_priority'] = $newData['priority'];
        }

        $sql .= " WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            Logger::info('Tarea reabierta', [
                'task_id' => $taskId,
                'reopened_by' => $userId,
                'motivo' => $motivo
            ]);
        }

        return $result;
    }

    /**
     * Guarda historial de reapertura.
     */
    private function saveReopenHistory(int $taskId, int $userId, string $motivo, ?string $observaciones, array $current, array $newData): void
    {
        try {
            $sql = "INSERT INTO task_reaperturas (
                        task_id, reopened_by, motivo, observaciones,
                        previous_status, previous_assigned_user_id, previous_deadline,
                        previous_priority, previous_completed_at,
                        new_assigned_user_id, new_deadline, new_priority,
                        reopened_at
                    ) VALUES (
                        :task_id, :reopened_by, :motivo, :observaciones,
                        :prev_status, :prev_assigned, :prev_deadline,
                        :prev_priority, :prev_completed,
                        :new_assigned, :new_deadline, :new_priority,
                        NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':task_id' => $taskId,
                ':reopened_by' => $userId,
                ':motivo' => $motivo,
                ':observaciones' => $observaciones,
                ':prev_status' => $current['status'],
                ':prev_assigned' => $current['assigned_user_id'],
                ':prev_deadline' => $current['deadline'],
                ':prev_priority' => $current['priority'],
                ':prev_completed' => $current['completed_at'],
                ':new_assigned' => $newData['assigned_user_id'] ?? $current['assigned_user_id'],
                ':new_deadline' => $newData['deadline'] ?? $current['deadline'],
                ':new_priority' => $newData['priority'] ?? $current['priority']
            ]);
        } catch (Exception $e) {
            Logger::warning('Failed to save reopen history: ' . $e->getMessage());
        }
    }

    /**
     * Elimina (inactiva) una tarea.
     */
    public function delete(int $taskId, ?int $userId = null): bool
    {
        $sql = "UPDATE tasks SET status = :inactive, updated_at = NOW() WHERE id = :task_id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':inactive' => TaskConfig::STATUS_INACTIVE,
            ':task_id' => $taskId
        ]);

        if ($result) {
            Logger::info('Tarea eliminada (inactivada)', ['task_id' => $taskId]);
        }

        return $result;
    }

    /**
     * Usuario se auto-asigna una tarea disponible del día actual.
     */
    public function selfAssign(int $taskId, int $userId): bool
    {
        $sql = "SELECT id FROM tasks 
                WHERE id = :task_id 
                  AND assigned_user_id IS NULL 
                  AND status = :pending
                  AND DATE(fecha_asignacion) = CURDATE()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':task_id' => $taskId,
            ':pending' => TaskConfig::STATUS_PENDING
        ]);

        if (!$stmt->fetch()) {
            return false;
        }

        return $this->assign($taskId, $userId, $userId);
    }

    /**
     * Completa una tarea con observaciones.
     */
    public function complete(int $taskId, int $userId, string $observaciones, ?string $evidencePath = null): bool
    {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE tasks 
                    SET status = :completed,
                        progreso = 100,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :task_id
                      AND assigned_user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':completed' => TaskConfig::STATUS_COMPLETED,
                ':task_id' => $taskId,
                ':user_id' => $userId
            ]);

            if ($result && $stmt->rowCount() > 0) {
                // Si hay evidencia, guardarla
                if ($evidencePath) {
                    $this->saveEvidence($taskId, $evidencePath, $userId);
                }
                
                $this->db->commit();
                Logger::info('Tarea completada', ['task_id' => $taskId, 'user_id' => $userId]);
                return true;
            }
            
            $this->db->rollBack();
            return false;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error al completar tarea', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Guarda una evidencia para una tarea
     */
    private function saveEvidence(int $taskId, string $filePath, int $userId): void
    {
        $sql = "INSERT INTO task_evidencias (task_id, archivo, tipo, uploaded_by, created_at)
                VALUES (:task_id, :archivo, 'imagen', :uploaded_by, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':task_id' => $taskId,
            ':archivo' => $filePath,
            ':uploaded_by' => $userId
        ]);
    }

    /**
     * Actualiza el progreso de una tarea.
     */
    public function updateProgress(int $taskId, int $userId, int $progreso): bool
    {
        $progreso = max(0, min(100, $progreso));
        $completed = TaskConfig::STATUS_COMPLETED;
        $inProcess = TaskConfig::STATUS_IN_PROCESS;

        $sql = "UPDATE tasks 
                SET progreso = :progreso,
                    status = CASE 
                        WHEN :progreso2 = 100 THEN '$completed'
                        WHEN :progreso3 > 0 THEN '$inProcess'
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
    // EVIDENCIAS (task_evidencias + evidencia_imagenes)
    // ============================================================

    /**
     * Determina el tipo de evidencia basado en el MIME type.
     * Usa constantes de TaskConfig.
     */
    private function getEvidenceTipo(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return TaskConfig::EVIDENCE_TYPE_IMAGE;
        } elseif (strpos($mimeType, 'application/pdf') === 0 || 
                  strpos($mimeType, 'application/msword') === 0 ||
                  strpos($mimeType, 'application/vnd.') === 0 ||
                  strpos($mimeType, 'text/') === 0) {
            return TaskConfig::EVIDENCE_TYPE_DOCUMENT;
        }
        return TaskConfig::EVIDENCE_TYPE_OTHER;
    }

    /**
     * Crea una evidencia para una tarea con observaciones.
     * Solo crea el registro padre en task_evidencias.
     * Las imágenes se agregan después con addImageToEvidence.
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario que sube
     * @param string $observaciones Observaciones de la evidencia
     * @return int|false ID de la evidencia creada o false
     */
    public function createEvidence(int $taskId, int $userId, string $observaciones)
    {
        try {
            $sql = "INSERT INTO task_evidencias (
                        task_id, archivo, tipo, observaciones, uploaded_by, created_at
                    ) VALUES (
                        :task_id, '', 'imagen', :observaciones, :uploaded_by, NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':task_id' => $taskId,
                ':observaciones' => $observaciones,
                ':uploaded_by' => $userId
            ]);

            $evidenciaId = (int)$this->db->lastInsertId();

            Logger::info('Evidencia creada', [
                'task_id' => $taskId, 
                'evidencia_id' => $evidenciaId,
                'user_id' => $userId
            ]);

            return $evidenciaId;

        } catch (Exception $e) {
            Logger::error('Error al crear evidencia: ' . $e->getMessage(), [
                'task_id' => $taskId
            ]);
            return false;
        }
    }

    /**
     * Agrega una imagen a una evidencia existente.
     * 
     * @param int $evidenciaId ID de la evidencia padre
     * @param string $filePath Ruta del archivo guardado
     * @param string $fileName Nombre original del archivo
     * @param int $fileSizeKb Tamaño en KB
     * @param string $mimeType Tipo MIME del archivo
     * @return int|false ID de la imagen creada o false
     */
    public function addImageToEvidence(
        int $evidenciaId,
        string $filePath, 
        string $fileName, 
        int $fileSizeKb, 
        string $mimeType
    ) {
        // Validar tamaño máximo
        if ($fileSizeKb > TaskConfig::MAX_FILE_SIZE_KB) {
            Logger::warning('Imagen rechazada: tamaño excedido', [
                'file' => $fileName, 
                'size_kb' => $fileSizeKb, 
                'max_kb' => TaskConfig::MAX_FILE_SIZE_KB
            ]);
            return false;
        }

        // Validar tipos permitidos
        if (!in_array($mimeType, TaskConfig::ALLOWED_MIME_TYPES)) {
            Logger::warning('Imagen rechazada: tipo no permitido', [
                'file' => $fileName, 
                'mime' => $mimeType
            ]);
            return false;
        }

        try {
            $sql = "INSERT INTO evidencia_imagenes (
                        evidencia_id, file_path, file_name, file_size_kb, mime_type, uploaded_at
                    ) VALUES (
                        :evidencia_id, :file_path, :file_name, :file_size_kb, :mime_type, NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':evidencia_id' => $evidenciaId,
                ':file_path' => $filePath,
                ':file_name' => $fileName,
                ':file_size_kb' => $fileSizeKb,
                ':mime_type' => $mimeType
            ]);

            $imagenId = (int)$this->db->lastInsertId();

            Logger::info('Imagen agregada a evidencia', [
                'evidencia_id' => $evidenciaId,
                'imagen_id' => $imagenId,
                'file' => $fileName
            ]);

            return $imagenId;

        } catch (Exception $e) {
            Logger::error('Error al agregar imagen: ' . $e->getMessage(), [
                'evidencia_id' => $evidenciaId,
                'file' => $fileName
            ]);
            return false;
        }
    }

    /**
     * Agrega evidencia completa a una tarea (método legacy para compatibilidad).
     * Crea evidencia con observaciones y agrega imagen.
     * 
     * @deprecated Usar createEvidence + addImageToEvidence
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
        $this->db->beginTransaction();

        try {
            // 1. Crear evidencia con observaciones
            $evidenciaId = $this->createEvidence($taskId, $userId, $observaciones ?? '');
            
            if (!$evidenciaId) {
                $this->db->rollBack();
                return false;
            }

            // 2. Agregar imagen
            $imagenId = $this->addImageToEvidence($evidenciaId, $filePath, $fileName, $fileSizeKb, $mimeType);
            
            if (!$imagenId) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return $evidenciaId;

        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error al agregar evidencia: ' . $e->getMessage(), [
                'task_id' => $taskId,
                'file' => $fileName
            ]);
            return false;
        }
    }

    /**
     * Agrega múltiples imágenes a una evidencia existente.
     * Útil para cuando se suben varias imágenes en una sola evidencia.
     * 
     * @param int $evidenciaId ID de la evidencia padre
     * @param array $images Array de ['file_path', 'file_name', 'file_size_kb', 'mime_type']
     * @return bool
     */
    public function addImagesToEvidence(int $evidenciaId, array $images): bool
    {
        $sql = "INSERT INTO evidencia_imagenes (
                    evidencia_id, file_path, file_name, file_size_kb, mime_type, uploaded_at
                ) VALUES (
                    :evidencia_id, :file_path, :file_name, :file_size_kb, :mime_type, NOW()
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($images as $img) {
            $result = $stmt->execute([
                ':evidencia_id' => $evidenciaId,
                ':file_path' => $img['file_path'],
                ':file_name' => $img['file_name'],
                ':file_size_kb' => $img['file_size_kb'],
                ':mime_type' => $img['mime_type']
            ]);

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene las evidencias de una tarea con sus imágenes asociadas.
     * 
     * Retorna estructura anidada:
     * [
     *   {
     *     id, tipo, nombre_original, tamanio, uploaded_by, created_at,
     *     imagenes: [{ id, file_path, file_name, file_size_kb, mime_type }]
     *   }
     * ]
     */
    public function getEvidenceByTaskId(int $taskId): array
    {
        // 1. Obtener evidencias padre
        $sqlEvidencias = "SELECT 
                            te.id,
                            te.archivo AS file_path,
                            te.tipo,
                            te.nombre_original AS file_name,
                            te.tamanio AS file_size_bytes,
                            te.uploaded_by AS uploaded_by_id,
                            CONCAT(u.nombre, ' ', u.apellido) AS uploaded_by_name,
                            te.created_at AS uploaded_at
                          FROM task_evidencias te
                          LEFT JOIN users u ON te.uploaded_by = u.id
                          WHERE te.task_id = :task_id
                          ORDER BY te.created_at DESC";

        $stmt = $this->db->prepare($sqlEvidencias);
        $stmt->execute([':task_id' => $taskId]);
        $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($evidencias)) {
            return [];
        }

        // Las evidencias de tasks ya no usan tabla de imágenes separada
        // Las imágenes ahora están en subtarea_evidencias
        return $evidencias;
    }

    /**
     * Obtiene imágenes de una evidencia específica.
     * Nota: Ahora las evidencias principales están en subtarea_evidencias
     */
    public function getImagesByEvidenceId(int $evidenciaId): array
    {
        // Las imágenes ahora están en subtarea_evidencias, no en tabla separada
        return [];
    }

    /**
     * Elimina una evidencia y sus imágenes asociadas (CASCADE).
     */
    public function deleteEvidence(int $evidenciaId, ?int $userId = null): bool
    {
        // ON DELETE CASCADE se encarga de eliminar evidencia_imagenes
        $sql = "DELETE FROM task_evidencias WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $evidenciaId]);

        if ($result) {
            Logger::info('Evidencia eliminada', [
                'evidencia_id' => $evidenciaId,
                'deleted_by' => $userId
            ]);
        }

        return $result;
    }

    /**
     * Cuenta evidencias de una tarea.
     */
    public function countEvidencesByTaskId(int $taskId): int
    {
        $sql = "SELECT COUNT(*) FROM task_evidencias WHERE task_id = :task_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Cuenta total de imágenes en todas las evidencias de una tarea.
     * Nota: Las evidencias ahora están en las subtareas
     */
    public function countImagesByTaskId(int $taskId): int
    {
        // Contar evidencias de subtareas en lugar de la tabla vieja
        $sql = "SELECT COUNT(*) 
                FROM subtarea_evidencias se
                INNER JOIN subtareas s ON se.subtarea_id = s.id
                WHERE s.task_id = :task_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);

        return (int)$stmt->fetchColumn();
    }

    // ============================================================
    // VERIFICACIONES
    // ============================================================

    /**
     * Verifica si una tarea existe.
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
     * Verifica si una tarea está disponible para asignar.
     */
    public function isAvailable(int $taskId): bool
    {
        $sql = "SELECT assigned_user_id, status FROM tasks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result 
            && $result['assigned_user_id'] === null 
            && $result['status'] === TaskConfig::STATUS_PENDING;
    }

    // ============================================================
    // UTILIDADES
    // ============================================================

    /**
     * Verifica si un usuario existe.
     */
    public function userExists(int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene el nombre completo de un usuario.
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
     * Obtiene usuarios disponibles para asignación.
     */
    public function getAvailableUsers(): array
    {
        $pending = TaskConfig::STATUS_PENDING;
        $inProcess = TaskConfig::STATUS_IN_PROCESS;
        $roleUser = TaskConfig::ROLE_USER;

        $sql = "SELECT 
                    u.id, 
                    u.username, 
                    CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS nombre_completo,
                    COALESCE(u.departamento, '') AS departamento,
                    COALESCE(u.estado, 'Disponible') AS estado,
                    (SELECT COUNT(*) FROM tasks t 
                     WHERE t.assigned_user_id = u.id 
                     AND t.status IN ('$pending', '$inProcess')) AS tareas_activas
                FROM users u
                WHERE u.role = '$roleUser' 
                  AND u.is_active = 1
                ORDER BY u.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza el estado de una tarea con validación.
     */
    public function updateStatus(int $taskId, string $status): bool
    {
        if (!TaskConfig::isValidStatus($status)) {
            return false;
        }

        $sql = "UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :task_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Marca tareas vencidas como inactivas automáticamente.
     */
    public function markOverdueTasks(): int
    {
        $inactive = TaskConfig::STATUS_INACTIVE;
        $pending = TaskConfig::STATUS_PENDING;
        $inProcess = TaskConfig::STATUS_IN_PROCESS;
        $days = TaskConfig::AUTO_INACTIVE_DAYS;

        $sql = "UPDATE tasks
                SET status = '$inactive',
                    updated_at = NOW()
                WHERE status IN ('$pending', '$inProcess')
                  AND (
                      DATE_ADD(fecha_asignacion, INTERVAL $days DAY) < CURDATE()
                      OR (deadline IS NOT NULL AND DATE_ADD(deadline, INTERVAL $days DAY) < CURDATE())
                  )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $count = $stmt->rowCount();
        if ($count > 0) {
            Logger::info("Tareas marcadas como inactivas: $count");
        }

        return $count;
    }

    /**
     * Obtiene subtareas de una tarea.
     */
    public function getSubtareasByTaskId(int $taskId): array
    {
        $sql = "SELECT * FROM subtareas WHERE task_id = :task_id ORDER BY created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene historial de reaperturas de una tarea.
     */
    public function getReopenHistory(int $taskId): array
    {
        $sql = "SELECT 
                    tr.*,
                    CONCAT(u.nombre, ' ', u.apellido) AS reopened_by_name
                FROM task_reaperturas tr
                LEFT JOIN users u ON tr.reopened_by = u.id
                WHERE tr.task_id = :task_id
                ORDER BY tr.reopened_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
