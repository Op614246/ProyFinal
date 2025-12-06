<?php
require_once __DIR__ . '/../core/TaskConfig.php';
class TaskRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    private function statusToLegacy(string $status): string
    {
        $map = defined('TaskConfig::STATUS_MAP') ? TaskConfig::STATUS_MAP : (defined('STATUS_MAP') ? constant('STATUS_MAP') : null);
        if (is_array($map)) {
            return $map[$status] ?? $status;
        }
        return $status;
    }

    private function statusToInternal(string $status): string
    {
        $map = defined('TaskConfig::STATUS_MAP') ? TaskConfig::STATUS_MAP : (defined('STATUS_MAP') ? constant('STATUS_MAP') : null);
        if (is_array($map)) {
            $reversed = array_flip($map);
            return $reversed[$status] ?? $status;
        }
        return $status;
    }

    private function priorityToLegacy(string $priority): string
    {
        $map = defined('TaskConfig::PRIORITY_MAP') ? TaskConfig::PRIORITY_MAP : (defined('PRIORITY_MAP') ? constant('PRIORITY_MAP') : null);
        if (is_array($map)) {
            return $map[$priority] ?? $priority;
        }
        return $priority;
    }

    private function priorityToInternal(string $priority): string
    {
        $map = defined('TaskConfig::PRIORITY_MAP') ? TaskConfig::PRIORITY_MAP : (defined('PRIORITY_MAP') ? constant('PRIORITY_MAP') : null);
        if (is_array($map)) {
            $reversed = array_flip($map);
            return $reversed[$priority] ?? $priority;
        }
        return $priority;
    }

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

    public function getTareas(?int $userId = null, array $filtros = []): array
    {
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
        
        // Filtrar tareas no eliminadas
        $conditions[] = "t.is_deleted = 0";
        
        if ($userId !== null) {
            $conditions[] = "t.assigned_user_id = ?";
            $params[] = $userId;
        }
        
        // Filtro por fecha de asignación (exacta)
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

        if (!empty($filtros['status'])) {
            $status = $this->statusToInternal($filtros['status']);
            $conditions[] = "t.status = ?";
            $params[] = $status;
        }

        if (!empty($filtros['priority'])) {
            $priority = $this->priorityToInternal($filtros['priority']);
            $conditions[] = "t.priority = ?";
            $params[] = $priority;
        }
        
        if (!empty($filtros['sucursal_id']) && $userId === null) {
            $conditions[] = "t.sucursal_id = ?";
            $params[] = $filtros['sucursal_id'];
        }
        
        if (!empty($filtros['categoria_id'])) {
            $conditions[] = "t.categoria_id = ?";
            $params[] = $filtros['categoria_id'];
        }
        
        if (!empty($filtros['assigned_user_id']) && $userId === null) {
            $conditions[] = "t.assigned_user_id = ?";
            $params[] = $filtros['assigned_user_id'];
        }
        
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
        
        Logger::debug('getTareas SQL', [
            'sql' => $sql,
            'params' => $params,
            'filtros' => $filtros
        ]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tareas = $stmt->fetchAll();
        
        foreach ($tareas as &$tarea) {
            $stats = $this->getSubtaskStats($tarea['id']);
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = (int)$stats['total'];
            $tarea['subtareasCompletadas'] = (int)$stats['completadas'];
        }
        
        return $tareas;
    }

    public function getAllTareasAdmin(): array { return $this->getTareas(null, []); }
    
    public function getTareasConFiltros(array $filtros = []): array { return $this->getTareas(null, $filtros); }
    
    public function getTareasAdminPorFecha(string $fecha): array { return $this->getTareas(null, ['fecha' => $fecha]); }
    
    public function getTareasAdminConFiltros(array $filtros = []): array { return $this->getTareas(null, $filtros); }
    
    public function getAllForUser(int $userId, array $filtros = []): array { return $this->getTareas($userId, $filtros); }

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
            $evidencias = $this->getEvidenceByTaskId($taskId);
            $tarea['evidencias'] = $evidencias;
            
            if (!empty($evidencias)) {
                $ultimaEvidencia = $evidencias[0];
                $tarea['observaciones'] = $ultimaEvidencia['observaciones'] ?? null;
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

    public function getTareaAdminPorId(int $taskId): ?array { return $this->getTareaById($taskId); }

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

    public function create(array $data, int $createdByUserId)
    {
        try {
            $this->db->beginTransaction();

            // Validar que deadline no sea antes que fecha_asignacion
            $fechaAsignacion = $data['fecha_asignacion'] ?? date('Y-m-d');
            $deadline = $data['deadline'] ?? TaskConfig::getDefaultDeadline($fechaAsignacion);
            
            if (strtotime($deadline) < strtotime($fechaAsignacion)) {
                $this->db->rollBack();
                throw new InvalidArgumentException('La fecha de vencimiento no puede ser anterior a la fecha de asignación');
            }

            $sql = "INSERT INTO tasks (
                        title, description, categoria_id, status, priority,
                        deadline, fecha_asignacion, horainicio, horafin,
                        assigned_user_id, created_by_user_id, sucursal_id,
                        is_deleted, created_at, updated_at
                    ) VALUES (
                        :title, :description, :categoria_id, :status, :priority,
                        :deadline, :fecha_asignacion, :horainicio, :horafin,
                        :assigned_user_id, :created_by_user_id, :sucursal_id,
                        0, NOW(), NOW()
                    )";

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
                
                $this->db->commit();
                return $taskId;
            }

            $this->db->rollBack();
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $taskId, array $data, int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            $oldData = $this->getTareaById($taskId);
            
            // Validar que deadline no sea antes que fecha_asignacion
            $fechaAsignacion = $data['fecha_asignacion'] ?? $oldData['fecha_asignacion'] ?? date('Y-m-d');
            $deadline = $data['deadline'] ?? $oldData['deadline'] ?? null;
            
            if ($deadline && strtotime($deadline) < strtotime($fechaAsignacion)) {
                $this->db->rollBack();
                throw new InvalidArgumentException('La fecha de vencimiento no puede ser anterior a la fecha de asignación');
            }
            
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
                $this->db->rollBack();
                return false;
            }

            $fields[] = "updated_at = NOW()";
            $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = :task_id AND is_deleted = 0";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                $this->logHistory($taskId, $userId, 'updated', json_encode($oldData), json_encode($data));
            }

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

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

    public function reopen(int $taskId, int $reopenedBy, string $motivo, ?string $observaciones = null, ?array $newValues = null): bool
    {
        $sqlCheck = "SELECT id, status, assigned_user_id, deadline, priority, completed_at 
                     FROM tasks WHERE id = ?";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([$taskId]);
        $task = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            return false;
        }
        
        if (!in_array($task['status'], ['completed', 'incomplete'])) {
            return false;
        }

        try {
            $this->db->beginTransaction();

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

    public function delete(int $taskId, int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE tasks SET is_deleted = 1, updated_at = NOW() WHERE id = :task_id AND is_deleted = 0";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':task_id' => $taskId]);

            if ($result) {
                $this->logHistory($taskId, $userId, 'soft_deleted', null, 'Tarea marcada como eliminada');
            }

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

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
            return false;
        }

        return $this->assign($taskId, $userId, $userId);
    }

    public function complete(int $taskId, int $userId, string $observaciones, ?string $evidencePath = null): bool
    {
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

    public function getEvidenceByTaskId(int $taskId): array
    {
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
        $evidencias = $stmt->fetchAll();

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

    public function exists(int $taskId): bool
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        return (int)$stmt->fetchColumn() > 0;
    }

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

    public function isAvailable(int $taskId): bool
    {
        $sql = "SELECT assigned_user_id FROM tasks WHERE id = :id AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['assigned_user_id'] === null;
    }

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

    public function puedeSerAsignada(int $taskId): bool { return $this->canBeAssigned($taskId); }

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

    public function userExists(int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getUsernameById(int $userId): ?string
    {
        $sql = "SELECT CONCAT(nombre, ' ', apellido) as fullname FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['fullname'] : null;
    }

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
            Logger::warning('Failed to log task history: ' . $e->getMessage());
        }
    }

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

    public function crearTareaAdmin(array $data, ?int $userId = null): int
    {
            $mapped = [];
            $mapped['title'] = $data['titulo'] ?? $data['title'] ?? '';
            $mapped['description'] = $data['descripcion'] ?? $data['description'] ?? null;
            $mapped['categoria_id'] = $this->resolveCategoriaId($data['Categoria'] ?? $data['categoria_id'] ?? null);
            $mapped['status'] = $this->statusToInternal($data['estado'] ?? 'Pendiente');
            $mapped['priority'] = $this->priorityToInternal($data['prioridad'] ?? 'Media');
            $mapped['fecha_asignacion'] = $data['fechaAsignacion'] ?? $data['fecha_asignacion'] ?? date('Y-m-d');
            $mapped['horainicio'] = $data['horainicio'] ?? null;
            $mapped['horafin'] = $data['horafin'] ?? null;
            $mapped['assigned_user_id'] = $data['usuarioasignado_id'] ?? $data['assigned_user_id'] ?? null;
            $mapped['sucursal_id'] = $this->resolveSucursalId($data['sucursal'] ?? $data['sucursal_id'] ?? null);
            $mapped['deadline'] = $data['fechaVencimiento'] ?? $data['deadline'] ?? null; // create() calculará si es null

            $taskId = $this->create($mapped, (int)($userId ?? 0));
            return (int)$taskId;
    }

    public function actualizarTareaAdmin(int $taskId, array $data): bool
    {
        $updateFields = [];
        $params = [];
        
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
        
        if (isset($data['estado'])) {
            $updateFields[] = "status = ?";
            $params[] = $this->statusToInternal($data['estado']);
        }
        
        if (isset($data['prioridad'])) {
            $updateFields[] = "priority = ?";
            $params[] = $this->priorityToInternal($data['prioridad']);
        }
        
        if (isset($data['Categoria'])) {
            $catId = $this->resolveCategoriaId($data['Categoria']);
            if ($catId) {
                $updateFields[] = "categoria_id = ?";
                $params[] = $catId;
            }
        }
        
        if (isset($data['sucursal'])) {
            $sucId = $this->resolveSucursalId($data['sucursal']);
            if ($sucId) {
                $updateFields[] = "sucursal_id = ?";
                $params[] = $sucId;
            }
        }
        
        if (empty($updateFields)) {
            $mapped = [];
            if (isset($data['titulo'])) $mapped['title'] = $data['titulo'];
            if (isset($data['descripcion'])) $mapped['description'] = $data['descripcion'];
            if (isset($data['fechaAsignacion'])) $mapped['fecha_asignacion'] = $data['fechaAsignacion'];
            if (isset($data['fechaVencimiento'])) $mapped['deadline'] = $data['fechaVencimiento'];
            if (isset($data['horainicio'])) $mapped['horainicio'] = $data['horainicio'];
            if (isset($data['horafin'])) $mapped['horafin'] = $data['horafin'];
            if (isset($data['progreso'])) $mapped['progreso'] = $data['progreso'];
            if (isset($data['usuarioasignado_id'])) $mapped['assigned_user_id'] = $data['usuarioasignado_id'];
            if (isset($data['estado'])) $mapped['status'] = $this->statusToInternal($data['estado']);
            if (isset($data['prioridad'])) $mapped['priority'] = $this->priorityToInternal($data['prioridad']);

            return $this->update($taskId, $mapped, 0);
        }

        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $taskId;
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        return (bool)$result;
    }

    public function iniciarTarea(int $taskId): bool
    {
        $sql = "UPDATE tasks SET status = 'in_process', updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$taskId]);
    }

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

    public function guardarEvidencias(int $taskId, int $userId, array $imagenes, ?string $observaciones = null)
    {
        if (empty($imagenes)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Crear UN SOLO registro en task_evidencias con las observaciones
            $sql = "INSERT INTO task_evidencias (
                        task_id, archivo, tipo, nombre_original, tamanio, 
                        observaciones, uploaded_by, created_at
                    ) VALUES (?, ?, 'imagen', ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            
            // Para task_evidencias usamos la ruta de la primera imagen como referencia
            // (puede ser NULL si hay múltiples imágenes)
            $firstImagePath = isset($imagenes[0]['path']) ? $imagenes[0]['path'] : null;
            $firstImageName = isset($imagenes[0]['name']) ? $imagenes[0]['name'] : 'múltiples imágenes';
            $totalSize = 0;
            foreach ($imagenes as $img) {
                $totalSize += isset($img['size']) ? (int)$img['size'] : 0;
            }
            
            $stmt->execute([
                $taskId, 
                $firstImagePath,
                $firstImageName, 
                $totalSize, 
                $observaciones, 
                $userId
            ]);
            
            $taskEvidenciaId = (int)$this->db->lastInsertId();
            $imagenesGuardadas = [];

            // Guardar cada imagen en evidencia_imagenes vinculada al task_evidencia
            foreach ($imagenes as $img) {
                $fileSizeBytes = isset($img['size']) ? (int)$img['size'] : 0;
                $fileSizeKb = round($fileSizeBytes / 1024, 2);

                if ($fileSizeKb > TaskConfig::MAX_FILE_SIZE_KB) {
                    throw new Exception("Archivo {$img['name']} excede el tamaño máximo");
                }

                // Validar tipo
                if (!in_array($img['type'], TaskConfig::ALLOWED_MIME_TYPES)) {
                    throw new Exception("Tipo de archivo no permitido: {$img['type']}");
                }

                // Insertar en evidencia_imagenes vinculada al task_evidencia
                $sqlImg = "INSERT INTO evidencia_imagenes (
                            task_evidencia_id, file_path, file_name, file_size_kb, mime_type, uploaded_at
                        ) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmtImg = $this->db->prepare($sqlImg);
                $stmtImg->execute([$taskEvidenciaId, $img['path'], $img['name'], $fileSizeKb, $img['type']]);
                
                $imagenesGuardadas[] = [
                    'file_path' => $img['path'],
                    'file_name' => $img['name'],
                    'file_size_kb' => $fileSizeKb
                ];
            }

            $this->db->commit();
            return $taskEvidenciaId;

        } catch (Exception $e) {
            $this->db->rollBack();
            Logger::error('Error al guardar evidencias: ' . $e->getMessage());
            return false;
        }
    }

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

    public function agregarEvidenciaConImagenes(int $taskId, int $userId, ?string $observaciones, array $imagenes)
    {
        return $this->guardarEvidencias($taskId, $userId, $imagenes, $observaciones);
    }

    public function marcarTareasIncompletas(): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'incomplete', updated_at = NOW() 
                WHERE status IN ('pending', 'in_process') 
                AND deadline < CURDATE()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }

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
