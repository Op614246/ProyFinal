<?php
/**
 * TaskRepository.php
 * 
 * Repositorio UNIFICADO para operaciones de tareas
 * Combina funcionalidades de Admin y User con roles diferenciados
 * Soporta formato interno (DB) y formato legacy (Frontend)
 * 
 * Estados internos: pending, in_process, completed, incomplete, inactive
 * Estados legacy: Pendiente, En progreso, Completada, Incompleta, Inactiva
 * 
 * Prioridades internas: high, medium, low
 * Prioridades legacy: Alta, Media, Baja
 */

class TaskRepository
{
    private $db;

    // Usar las constantes globales definidas en config.php
    // STATUS_MAP y PRIORITY_MAP ya están definidos allí

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    // ============================================================
    // SECCIÓN: UTILIDADES DE MAPEO
    // ============================================================

    /**
     * Convierte estado interno a formato legacy
     */
    private function statusToLegacy(string $status): string
    {
        return STATUS_MAP[$status] ?? $status;
    }

    /**
     * Convierte estado legacy a formato interno
     */
    private function statusToInternal(string $status): string
    {
        $reversed = array_flip(STATUS_MAP);
        return $reversed[$status] ?? $status;
    }

    /**
     * Convierte prioridad interna a formato legacy
     */
    private function priorityToLegacy(string $priority): string
    {
        return PRIORITY_MAP[$priority] ?? $priority;
    }

    /**
     * Convierte prioridad legacy a formato interno
     */
    private function priorityToInternal(string $priority): string
    {
        $reversed = array_flip(PRIORITY_MAP);
        return $reversed[$priority] ?? $priority;
    }

    /**
     * Obtiene estadísticas de subtareas de una tarea
     */
    private function getSubtaskStats(int $taskId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) as completadas
                FROM subtareas 
                WHERE task_id = ? AND is_deleted = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'completadas' => 0];
    }

    /**
     * Busca categoría por ID o nombre
     */
    private function resolveCategoriaId($categoria): ?int
    {
        if (empty($categoria)) return null;
        
        if (is_numeric($categoria)) {
            return (int) $categoria;
        }
        
        $stmt = $this->db->prepare('SELECT id FROM categorias WHERE nombre = ?');
        $stmt->execute([$categoria]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * Busca sucursal por ID o nombre
     */
    private function resolveSucursalId($sucursal): ?int
    {
        if (empty($sucursal)) return null;
        
        if (is_numeric($sucursal)) {
            return (int) $sucursal;
        }
        
        $stmt = $this->db->prepare('SELECT id FROM sucursales WHERE nombre = ?');
        $stmt->execute([$sucursal]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ============================================================
    // SECCIÓN: CONSULTA PRINCIPAL UNIFICADA
    // ============================================================

    /**
     * Obtiene tareas según rol del usuario
     * - Admin (userId = null): Ve todas las tareas
     * - User (userId != null): Ve solo las tareas asignadas a él
     * 
     * @param int|null $userId ID del usuario (null para admin)
     * @param array $filtros Filtros opcionales
     * @return array Lista de tareas en formato legacy
     */
    public function getTareas(?int $userId = null, array $filtros = []): array
    {
        // Auto-inactivar tareas vencidas antes de consultar
        $this->inactivarTareasVencidas();
        
        $conditions = [];
        $params = [];
        
        $sql = "SELECT 
                    t.id, 
                    t.title as titulo, 
                    CASE t.status
                        WHEN 'pending' THEN 'Pendiente'
                        WHEN 'in_process' THEN 'En progreso'
                        WHEN 'completed' THEN 'Completada'
                        WHEN 'incomplete' THEN 'Incompleta'
                        WHEN 'inactive' THEN 'Inactiva'
                        ELSE t.status
                    END as estado,
                    t.status as status_internal,
                    DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion, 
                    t.horainicio as horaprogramada, 
                    t.horainicio, 
                    t.horafin, 
                    s.nombre as sucursal,
                    c.nombre as Categoria,
                    c.id as categoria_id,
                    s.id as sucursal_id,
                    uc.nombre as created_by_nombre, 
                    uc.apellido as created_by_apellido,
                    t.description as descripcion,
                    t.priority as prioridad,
                    CASE t.priority
                        WHEN 'high' THEN 'Alta'
                        WHEN 'medium' THEN 'Media'
                        WHEN 'low' THEN 'Baja'
                        ELSE t.priority
                    END as Prioridad,
                    t.deadline as fechaVencimiento,
                    t.progreso,
                    t.completed_at as fechaCompletado,
                    ua.id as usuarioasignado_id,
                    CONCAT(ua.nombre, ' ', ua.apellido) as usuarioasignado,
                    t.assigned_user_id,
                    t.created_by_user_id,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                LEFT JOIN users uc ON t.created_by_user_id = uc.id
                LEFT JOIN users ua ON t.assigned_user_id = ua.id";
        
        // IMPORTANTE: Excluir tareas eliminadas (soft delete)
        $conditions[] = "t.is_deleted = 0";
        
        // Si es usuario (no admin), restringir a sus tareas asignadas
        if ($userId !== null) {
            $conditions[] = "t.assigned_user_id = ?";
            $params[] = $userId;
        }
        
        // Filtro por fecha
        if (!empty($filtros['fecha'])) {
            $conditions[] = "DATE(t.fecha_asignacion) = ?";
            $params[] = $filtros['fecha'];
        }
        
        // Filtro por rango de fechas
        if (!empty($filtros['fecha_inicio'])) {
            $conditions[] = "DATE(t.fecha_asignacion) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $conditions[] = "DATE(t.fecha_asignacion) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        // Filtro por estado (acepta legacy o interno)
        if (!empty($filtros['status'])) {
            $status = $this->statusToInternal($filtros['status']);
            $conditions[] = "t.status = ?";
            $params[] = $status;
        }
        
        // Filtro por prioridad (acepta legacy o interno)
        if (!empty($filtros['priority'])) {
            $priority = $this->priorityToInternal($filtros['priority']);
            $conditions[] = "t.priority = ?";
            $params[] = $priority;
        }
        
        // Filtro por sucursal (solo admin puede filtrar por sucursal)
        if (!empty($filtros['sucursal_id']) && $userId === null) {
            $conditions[] = "t.sucursal_id = ?";
            $params[] = $filtros['sucursal_id'];
        }
        
        // Filtro por categoría
        if (!empty($filtros['categoria_id'])) {
            $conditions[] = "t.categoria_id = ?";
            $params[] = $filtros['categoria_id'];
        }
        
        // Filtro por usuario asignado (solo admin puede filtrar por usuario)
        if (!empty($filtros['assigned_user_id']) && $userId === null) {
            $conditions[] = "t.assigned_user_id = ?";
            $params[] = $filtros['assigned_user_id'];
        }
        
        // Filtro tareas sin asignar (solo admin)
        if (!empty($filtros['sin_asignar']) && $userId === null) {
            $conditions[] = "t.assigned_user_id IS NULL";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY 
            FIELD(t.status, 'in_process', 'pending', 'incomplete', 'inactive', 'completed'),
            FIELD(t.priority, 'high', 'medium', 'low'),
            t.horainicio ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar conteo de subtareas
        foreach ($tareas as &$tarea) {
            $stats = $this->getSubtaskStats($tarea['id']);
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = (int)$stats['total'];
            $tarea['subtareasCompletadas'] = (int)$stats['completadas'];
        }
        
        return $tareas;
    }

    // ============================================================
    // ALIAS LEGACY - Usar getTareas() directamente en nuevo código
    // ============================================================
    
    /** @deprecated Usar getTareas(null, []) */
    public function getAllTareasAdmin(): array { return $this->getTareas(null, []); }
    
    /** @deprecated Usar getTareas(null, $filtros) */
    public function getTareasConFiltros(array $filtros = []): array { return $this->getTareas(null, $filtros); }
    
    /** @deprecated Usar getTareas(null, ['fecha' => $fecha]) */
    public function getTareasAdminPorFecha(string $fecha): array { return $this->getTareas(null, ['fecha' => $fecha]); }
    
    /** @deprecated Usar getTareas(null, $filtros) - Duplicado de getTareasConFiltros */
    public function getTareasAdminConFiltros(array $filtros = []): array { return $this->getTareas(null, $filtros); }
    
    /** @deprecated Usar getTareas($userId, $filtros) */
    public function getAllForUser(int $userId, array $filtros = []): array { return $this->getTareas($userId, $filtros); }

    /**
     * Obtiene una tarea por ID en formato legacy
     */
    public function getTareaById(int $taskId): ?array
    {
        $sql = "SELECT 
                    t.id, 
                    t.title as titulo, 
                    CASE t.status
                        WHEN 'pending' THEN 'Pendiente'
                        WHEN 'in_process' THEN 'En progreso'
                        WHEN 'completed' THEN 'Completada'
                        WHEN 'incomplete' THEN 'Incompleta'
                        WHEN 'inactive' THEN 'Inactiva'
                        ELSE t.status
                    END as estado,
                    t.status as status_internal,
                    DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion, 
                    t.horainicio as horaprogramada, 
                    t.horainicio, 
                    t.horafin, 
                    s.nombre as sucursal,
                    c.nombre as Categoria,
                    c.id as categoria_id,
                    s.id as sucursal_id,
                    uc.nombre as created_by_nombre, 
                    uc.apellido as created_by_apellido,
                    t.description as descripcion,
                    t.priority as prioridad,
                    CASE t.priority
                        WHEN 'high' THEN 'Alta'
                        WHEN 'medium' THEN 'Media'
                        WHEN 'low' THEN 'Baja'
                        ELSE t.priority
                    END as Prioridad,
                    t.deadline as fechaVencimiento,
                    t.progreso,
                    t.completed_at as fechaCompletado,
                    ua.id as usuarioasignado_id,
                    CONCAT(ua.nombre, ' ', ua.apellido) as usuarioasignado,
                    t.assigned_user_id,
                    t.created_by_user_id,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                LEFT JOIN users uc ON t.created_by_user_id = uc.id
                LEFT JOIN users ua ON t.assigned_user_id = ua.id
                WHERE t.id = ? AND t.is_deleted = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tarea) {
            $stats = $this->getSubtaskStats($taskId);
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = (int)$stats['total'];
            $tarea['subtareasCompletadas'] = (int)$stats['completadas'];
            // Las evidencias vienen de evidencia_imagenes
            $evidencias = $this->getEvidenceByTaskId($taskId);
            $tarea['evidencias'] = $evidencias;
            
            // Para compatibilidad con frontend: extraer observaciones e imágenes de la última evidencia
            if (!empty($evidencias)) {
                $ultimaEvidencia = $evidencias[0]; // Ya viene ordenado DESC
                $tarea['observaciones'] = $ultimaEvidencia['observaciones'] ?? null;
                // Extraer rutas de imágenes en formato simple
                $imagenes = [];
                foreach ($evidencias as $ev) {
                    foreach ($ev['imagenes'] ?? [] as $img) {
                        $imagenes[] = $img['file_path'];
                    }
                }
                $tarea['imagenes'] = $imagenes;
            } else {
                $tarea['observaciones'] = null;
                $tarea['imagenes'] = [];
            }
        }
        
        return $tarea ?: null;
    }

    /** @deprecated Usar getTareaById() */
    public function getTareaAdminPorId(int $taskId): ?array { return $this->getTareaById($taskId); }

    /**
     * Obtiene tareas disponibles para auto-asignación (solo del día actual)
     */
    public function getAvailableTasksForToday(): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title as titulo,
                    t.description as descripcion,
                    t.categoria_id,
                    c.nombre AS Categoria,
                    t.priority as prioridad,
                    CASE t.priority
                        WHEN 'high' THEN 'Alta'
                        WHEN 'medium' THEN 'Media'
                        WHEN 'low' THEN 'Baja'
                        ELSE t.priority
                    END as Prioridad,
                    t.deadline as fechaVencimiento,
                    DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion,
                    t.horainicio,
                    t.horafin,
                    t.sucursal_id,
                    s.nombre AS sucursal
                FROM tasks t
                LEFT JOIN categorias c ON t.categoria_id = c.id
                LEFT JOIN sucursales s ON t.sucursal_id = s.id
                WHERE t.assigned_user_id IS NULL
                  AND t.status = 'pending'
                  AND DATE(t.fecha_asignacion) = CURDATE()
                ORDER BY 
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    t.horainicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // SECCIÓN: ESTADÍSTICAS
    // ============================================================

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
        $oldData = $this->getTareaById($taskId);
        
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
     * @param int $reopenedBy ID del usuario que reabre
     * @param string $motivo Motivo de reapertura
     * @param string|null $observaciones Observaciones adicionales
     * @param array|null $newValues Nuevos valores opcionales (assigned_user_id, deadline, priority)
     * @return bool
     */
    public function reopen(int $taskId, int $reopenedBy, string $motivo, ?string $observaciones = null, ?array $newValues = null): bool
    {
        // Obtener datos actuales de la tarea
        $sqlCheck = "SELECT id, status, assigned_user_id, deadline, priority, completed_at 
                     FROM tasks WHERE id = ?";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([$taskId]);
        $task = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            return false;
        }
        
        // Solo se pueden reabrir tareas completadas o incompletas
        if (!in_array($task['status'], ['completed', 'incomplete'])) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // 1. Insertar registro en task_reaperturas con historial completo
            $sqlReapertura = "INSERT INTO task_reaperturas (
                                task_id, reopened_by, reopened_at, motivo, observaciones,
                                previous_status, previous_assigned_user_id, previous_deadline, 
                                previous_priority, previous_completed_at,
                                new_assigned_user_id, new_deadline, new_priority
                            ) VALUES (
                                :task_id, :reopened_by, NOW(), :motivo, :observaciones,
                                :previous_status, :previous_assigned_user_id, :previous_deadline,
                                :previous_priority, :previous_completed_at,
                                :new_assigned_user_id, :new_deadline, :new_priority
                            )";

            $newAssignedUserId = $newValues['assigned_user_id'] ?? $task['assigned_user_id'];
            $newDeadline = $newValues['deadline'] ?? $task['deadline'];
            $newPriority = $newValues['priority'] ?? $task['priority'];

            $stmtReapertura = $this->db->prepare($sqlReapertura);
            $stmtReapertura->execute([
                ':task_id' => $taskId,
                ':reopened_by' => $reopenedBy,
                ':motivo' => $motivo,
                ':observaciones' => $observaciones,
                ':previous_status' => $task['status'],
                ':previous_assigned_user_id' => $task['assigned_user_id'],
                ':previous_deadline' => $task['deadline'],
                ':previous_priority' => $task['priority'],
                ':previous_completed_at' => $task['completed_at'],
                ':new_assigned_user_id' => $newAssignedUserId,
                ':new_deadline' => $newDeadline,
                ':new_priority' => $newPriority
            ]);

            // 2. Actualizar la tarea
            $sql = "UPDATE tasks 
                    SET status = 'pending',
                        completed_at = NULL,
                        progreso = 0,
                        reopened_at = NOW(),
                        reabierta_por = :reopened_by,
                        assigned_user_id = :assigned_user_id,
                        deadline = :deadline,
                        priority = :priority,
                        updated_at = NOW()
                    WHERE id = :task_id";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':reopened_by' => $reopenedBy,
                ':assigned_user_id' => $newAssignedUserId,
                ':deadline' => $newDeadline,
                ':priority' => $newPriority,
                ':task_id' => $taskId
            ]);

            if ($result && $stmt->rowCount() > 0) {
                $this->db->commit();
                $this->logHistory($taskId, $reopenedBy, 'reopened', $task['status'], "Motivo: $motivo");
                return true;
            }

            $this->db->rollBack();
            return false;

        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error al reabrir tarea: ' . $e->getMessage());
            return false;
        }
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
        // Soft delete: marcar como eliminado usando is_deleted = 1
        $sql = "UPDATE tasks SET is_deleted = 1, updated_at = NOW() WHERE id = :task_id AND is_deleted = 0";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':task_id' => $taskId]);

        if ($result) {
            $this->logHistory($taskId, $userId, 'soft_deleted', null, 'Tarea marcada como eliminada');
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
        // Solo actualizar estado en tasks, las evidencias ya están en task_evidencias
        $sql = "UPDATE tasks 
                SET status = 'completed',
                    progreso = 100,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :task_id
                  AND assigned_user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
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
     * @deprecated Usar guardarEvidencias() - Mantiene compatibilidad con código nuevo
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
        $imagenes = [[
            'path' => $filePath,
            'name' => $fileName,
            'size' => $fileSizeKb * 1024, // Convertir KB a bytes
            'type' => $mimeType
        ]];
        $result = $this->guardarEvidencias($taskId, $userId, $imagenes, $observaciones);
        return $result ? $result[0] : false;
    }

    /**
     * Obtiene las evidencias de una tarea con sus imágenes
     * 
     * @param int $taskId ID de la tarea
     * @return array Lista de evidencias con imágenes
     */
    public function getEvidenceByTaskId(int $taskId): array
    {
        // Obtener evidencias
        $sql = "SELECT 
                    te.id,
                    te.task_id,
                    te.observaciones,
                    te.created_at as uploaded_at,
                    u.username,
                    CONCAT(u.nombre, ' ', u.apellido) AS uploaded_by_name
                FROM task_evidencias te
                LEFT JOIN users u ON te.uploaded_by = u.id
                WHERE te.task_id = :task_id
                ORDER BY te.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':task_id' => $taskId]);
        $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener imágenes para cada evidencia
        foreach ($evidencias as &$evidencia) {
            $sqlImg = "SELECT 
                            id,
                            file_path,
                            file_name,
                            file_size_kb,
                            mime_type,
                            uploaded_at
                        FROM evidencia_imagenes
                        WHERE task_evidencia_id = :evidencia_id
                        ORDER BY uploaded_at ASC";
            
            $stmtImg = $this->db->prepare($sqlImg);
            $stmtImg->execute([':evidencia_id' => $evidencia['id']]);
            $evidencia['imagenes'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        }

        return $evidencias;
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
     * Verifica si una tarea puede ser asignada (del mismo día y sin asignar)
     */
    public function canBeAssigned(int $taskId): bool
    {
        $sql = "SELECT COUNT(*) FROM tasks 
                WHERE id = ? 
                AND assigned_user_id IS NULL 
                AND DATE(fecha_asignacion) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchColumn() > 0;
    }

    /** @deprecated Usar canBeAssigned() */
    public function puedeSerAsignada(int $taskId): bool { return $this->canBeAssigned($taskId); }

    /**
     * Obtiene tareas disponibles (sin asignar) para usuarios
     */
    public function getAvailableTasks(): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.title as titulo,
                    t.description,
                    t.description as descripcion,
                    t.categoria_id,
                    c.nombre AS categoria_nombre,
                    c.nombre AS Categoria,
                    t.priority,
                    CASE t.priority
                        WHEN 'high' THEN 'Alta'
                        WHEN 'medium' THEN 'Media'
                        WHEN 'low' THEN 'Baja'
                        ELSE t.priority
                    END as Prioridad,
                    t.deadline,
                    t.deadline as fechaVencimiento,
                    t.fecha_asignacion,
                    DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion,
                    t.horainicio,
                    t.horafin,
                    t.sucursal_id,
                    s.nombre AS sucursal_nombre,
                    s.nombre AS sucursal
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
            ':status' => $this->statusToInternal($status),
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

    // ============================================================
    // SECCIÓN: MÉTODOS LEGACY PARA COMPATIBILIDAD CON ADMIN
    // ============================================================

    /**
     * Crear tarea en formato legacy (Admin)
     */
    public function crearTareaAdmin(array $data, ?int $userId = null): int
    {
        // Resolver categoria_id
        $categoriaId = $this->resolveCategoriaId($data['Categoria'] ?? $data['categoria_id'] ?? null);
        
        // Resolver sucursal_id
        $sucursalId = $this->resolveSucursalId($data['sucursal'] ?? $data['sucursal_id'] ?? null);
        
        // Mapear estado legacy a interno
        $status = $this->statusToInternal($data['estado'] ?? 'Pendiente');
        
        // Mapear prioridad legacy a interno
        $priority = $this->priorityToInternal($data['prioridad'] ?? 'Media');
        
        $sql = "INSERT INTO tasks (
                    title, description, categoria_id, status, priority,
                    deadline, fecha_asignacion, horainicio, horafin,
                    assigned_user_id, created_by_user_id, sucursal_id,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $fechaAsignacion = $data['fechaAsignacion'] ?? $data['fecha_asignacion'] ?? date('Y-m-d');
        $deadline = $data['fechaVencimiento'] ?? $data['deadline'] ?? date('Y-m-d', strtotime($fechaAsignacion . ' +2 days'));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['titulo'] ?? $data['title'] ?? '',
            $data['descripcion'] ?? $data['description'] ?? null,
            $categoriaId,
            $status,
            $priority,
            $deadline,
            $fechaAsignacion,
            $data['horainicio'] ?? null,
            $data['horafin'] ?? null,
            $data['usuarioasignado_id'] ?? $data['assigned_user_id'] ?? null,
            $userId,
            $sucursalId
        ]);
        
        $taskId = (int) $this->db->lastInsertId();
        
        if ($taskId && $userId) {
            $this->logHistory($taskId, $userId, 'created', null, json_encode($data));
        }
        
        return $taskId;
    }

    /**
     * Actualizar tarea en formato legacy (Admin)
     */
    public function actualizarTareaAdmin(int $taskId, array $data): bool
    {
        $updateFields = [];
        $params = [];
        
        // Mapear campos del frontend al schema de BD
        $fieldMap = [
            'titulo' => 'title',
            'descripcion' => 'description',
            'fechaAsignacion' => 'fecha_asignacion',
            'fechaVencimiento' => 'deadline',
            'horainicio' => 'horainicio',
            'horafin' => 'horafin',
            'progreso' => 'progreso',
            'usuarioasignado_id' => 'assigned_user_id'
        ];
        
        foreach ($fieldMap as $legacyField => $dbField) {
            if (isset($data[$legacyField])) {
                $updateFields[] = "$dbField = ?";
                $params[] = $data[$legacyField];
            }
        }
        
        // Mapear estado
        if (isset($data['estado'])) {
            $updateFields[] = "status = ?";
            $params[] = $this->statusToInternal($data['estado']);
        }
        
        // Mapear prioridad
        if (isset($data['prioridad'])) {
            $updateFields[] = "priority = ?";
            $params[] = $this->priorityToInternal($data['prioridad']);
        }
        
        // Manejar categoría por nombre
        if (isset($data['Categoria'])) {
            $catId = $this->resolveCategoriaId($data['Categoria']);
            if ($catId) {
                $updateFields[] = "categoria_id = ?";
                $params[] = $catId;
            }
        }
        
        // Manejar sucursal por nombre
        if (isset($data['sucursal'])) {
            $sucId = $this->resolveSucursalId($data['sucursal']);
            if ($sucId) {
                $updateFields[] = "sucursal_id = ?";
                $params[] = $sucId;
            }
        }
        
        if (empty($updateFields)) {
            return true;
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $taskId;
        
        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Eliminar tarea (hard delete) - Admin
     */
    public function eliminarTareaAdmin(int $taskId): bool
    {
        try {
            // Soft delete: marcar como eliminado usando is_deleted = 1
            // También marcar subtareas como eliminadas
            $stmtSub = $this->db->prepare("UPDATE subtareas SET is_deleted = 1, updated_at = NOW() WHERE task_id = ? AND is_deleted = 0");
            $stmtSub->execute([$taskId]);
            
            // Marcar la tarea como eliminada
            $stmt = $this->db->prepare("UPDATE tasks SET is_deleted = 1, updated_at = NOW() WHERE id = ? AND is_deleted = 0");
            return $stmt->execute([$taskId]);
        } catch (Exception $e) {
            Logger::error('Error eliminando tarea (soft delete): ' . $e->getMessage());
            return false;
        }
    }

    /** @deprecated Usar getTareas(null, ['sucursal_id' => $sucursalId]) */
    public function getTareasAdminPorSucursal(int $sucursalId): array { return $this->getTareas(null, ['sucursal_id' => $sucursalId]); }

    /**
     * Asignar tarea a un usuario (legacy)
     */
    public function asignarTarea(int $taskId, int $userId): bool
    {
        $sql = "UPDATE tasks SET assigned_user_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$userId, $taskId]);
        
        if ($result) {
            $this->updateUserTaskCount($userId, 1);
        }
        
        return $result;
    }

    /**
     * Iniciar tarea (cambiar estado a 'in_process')
     */
    public function iniciarTarea(int $taskId): bool
    {
        $sql = "UPDATE tasks SET status = 'in_process', updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$taskId]);
    }

    /**
     * Completar tarea con observaciones y evidencia (legacy)
     * Las observaciones e imágenes van a task_evidencias, aquí solo marcamos completada
     */
    public function completarTarea(int $taskId, string $observaciones, ?string $imagePath = null): bool
    {
        $sql = "UPDATE tasks SET 
                    status = 'completed', 
                    completed_at = NOW(),
                    progreso = 100,
                    updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$taskId]);
    }

    // ============================================================
    // SECCIÓN: EVIDENCIAS - MÉTODO UNIFICADO
    // ============================================================

    /**
     * Agrega evidencia(s) a una tarea
     * Método unificado que soporta una o múltiples imágenes
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario que sube
     * @param array $imagenes Array de imágenes [['path'=>, 'name'=>, 'size'=>, 'type'=>], ...]
     * @param string|null $observaciones Observaciones
     * @return array|false Array con IDs de evidencias o false si falla
     */
    public function guardarEvidencias(int $taskId, int $userId, array $imagenes, ?string $observaciones = null)
    {
        if (empty($imagenes)) {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $evidenciaIds = [];

            foreach ($imagenes as $img) {
                $fileSizeBytes = isset($img['size']) ? (int)$img['size'] : 0;
                $fileSizeKb = round($fileSizeBytes / 1024, 2);

                // Validar tamaño
                if ($fileSizeKb > MAX_FILE_SIZE_KB) {
                    throw new Exception("Archivo {$img['name']} excede el tamaño máximo");
                }

                // Validar tipo
                if (!in_array($img['type'], ALLOWED_IMAGE_TYPES)) {
                    throw new Exception("Tipo de archivo no permitido: {$img['type']}");
                }

                // Insertar en task_evidencias
                $sql = "INSERT INTO task_evidencias (
                            task_id, archivo, tipo, nombre_original, tamanio, 
                            observaciones, uploaded_by, created_at
                        ) VALUES (?, ?, 'imagen', ?, ?, ?, ?, NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $taskId, $img['path'], $img['name'], $fileSizeBytes, $observaciones, $userId
                ]);
                
                $evidenciaId = (int)$this->db->lastInsertId();
                $evidenciaIds[] = $evidenciaId;

                // Insertar en evidencia_imagenes
                $sqlImg = "INSERT INTO evidencia_imagenes (
                            task_evidencia_id, file_path, file_name, file_size_kb, mime_type, uploaded_at
                        ) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmtImg = $this->db->prepare($sqlImg);
                $stmtImg->execute([$evidenciaId, $img['path'], $img['name'], $fileSizeKb, $img['type']]);
            }

            $this->db->commit();
            return $evidenciaIds;

        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error al guardar evidencias: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @deprecated Usar guardarEvidencias() - Mantiene compatibilidad
     */
    public function agregarEvidencia(int $taskId, int $userId, string $filePath, string $fileName, int $fileSize, string $mimeType, ?string $observaciones = null): bool
    {
        $imagenes = [[
            'path' => $filePath,
            'name' => $fileName,
            'size' => $fileSize,
            'type' => $mimeType
        ]];
        return $this->guardarEvidencias($taskId, $userId, $imagenes, $observaciones) !== false;
    }

    /**
     * @deprecated Usar guardarEvidencias() - Mantiene compatibilidad
     */
    public function agregarEvidenciaConImagenes(int $taskId, int $userId, ?string $observaciones, array $imagenes)
    {
        return $this->guardarEvidencias($taskId, $userId, $imagenes, $observaciones);
    }

    /**
     * Marcar tareas como incompletas (para cron job)
     */
    public function marcarTareasIncompletas(): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'incomplete', updated_at = NOW() 
                WHERE status IN ('pending', 'in_process') 
                AND deadline < CURDATE()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }

    /**
     * Inactivar tareas vencidas automáticamente
     * Las tareas que no se completaron hasta el día después de la fecha_asignacion se inactivan
     * También tareas con deadline vencido
     */
    public function inactivarTareasVencidas(): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'inactive', updated_at = NOW() 
                WHERE status IN ('pending', 'in_process') 
                AND (
                    DATE_ADD(fecha_asignacion, INTERVAL 1 DAY) < CURDATE()
                    OR (deadline IS NOT NULL AND DATE_ADD(deadline, INTERVAL 1 DAY) < CURDATE())
                )";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
}
