<?php
/**
 * TaskAdminRepository.php
 * 
 * Repositorio para tareas admin usando tabla 'tasks' normalizada
 * Mapea los campos al formato legacy para compatibilidad con frontend
 */

class TaskAdminRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener todas las tareas (formato admin legacy)
     */
    public function getAllTareasAdmin() {
        $sql = "
            SELECT 
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
                DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion, 
                t.horainicio as horaprogramada, 
                t.horainicio, 
                t.horafin, 
                s.nombre as sucursal,
                c.nombre as Categoria,
                uc.nombre as created_by_nombre, 
                uc.apellido as created_by_apellido,
                t.description as descripcion,
                t.priority as prioridad,
                t.deadline as fechaVencimiento,
                t.progreso,
                t.completed_at as fechaCompletado,
                t.completion_notes as observaciones,
                t.evidence_image as imagenes,
                t.motivo_reapertura as motivoReapertura,
                t.observaciones_reapertura as observacionesReapertura,
                ua.id as usuarioasignado_id,
                CONCAT(ua.nombre, ' ', ua.apellido) as usuarioasignado
            FROM tasks t
            LEFT JOIN categorias c ON t.categoria_id = c.id
            LEFT JOIN sucursales s ON t.sucursal_id = s.id
            LEFT JOIN users uc ON t.created_by_user_id = uc.id
            LEFT JOIN users ua ON t.assigned_user_id = ua.id
            ORDER BY t.fecha_asignacion DESC, t.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar conteo real de subtareas
        foreach ($tareasAdmin as &$tarea) {
            $stats = $this->getEstadisticasSubtareas($tarea['id']);
            $tarea['Tarea'] = []; // Mantener compatibilidad con frontend
            $tarea['totalSubtareas'] = (int)$stats['total'];
            $tarea['subtareasCompletadas'] = (int)$stats['completadas'];
        }
        
        return $tareasAdmin;
    }
    
    /**
     * Obtener estadísticas de subtareas de una tarea
     */
    private function getEstadisticasSubtareas($taskId) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completada = 1 OR estado = 'Completada' THEN 1 ELSE 0 END) as completadas
            FROM subtareas 
            WHERE task_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'completadas' => 0];
    }
    
    /**
     * Obtener tareas por fecha (formato admin legacy)
     */
    public function getTareasAdminPorFecha($fecha) {
        return $this->getTareasAdminConFiltros(['fecha' => $fecha]);
    }

    /**
     * Obtener tareas con filtros dinámicos
     * @param array $filtros - fecha, status, sucursal_id, categoria_id, assigned_user_id, sin_asignar
     */
    public function getTareasAdminConFiltros($filtros = []) {
        $conditions = [];
        $params = [];
        
        // Base SQL
        $sql = "
            SELECT 
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
                DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion, 
                t.horainicio as horaprogramada, 
                t.horainicio, 
                t.horafin, 
                s.nombre as sucursal,
                c.nombre as Categoria,
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
                t.completion_notes as observaciones,
                t.evidence_image as imagenes,
                t.motivo_reapertura as motivoReapertura,
                t.observaciones_reapertura as observacionesReapertura,
                ua.id as usuarioasignado_id,
                CONCAT(ua.nombre, ' ', ua.apellido) as usuarioasignado
            FROM tasks t
            LEFT JOIN categorias c ON t.categoria_id = c.id
            LEFT JOIN sucursales s ON t.sucursal_id = s.id
            LEFT JOIN users uc ON t.created_by_user_id = uc.id
            LEFT JOIN users ua ON t.assigned_user_id = ua.id
        ";
        
        // Filtro por fecha
        if (!empty($filtros['fecha'])) {
            $conditions[] = "DATE(t.fecha_asignacion) = ?";
            $params[] = $filtros['fecha'];
        }
        
        // Filtro por estado
        if (!empty($filtros['status'])) {
            $statusMap = [
                'Pendiente' => 'pending',
                'En progreso' => 'in_process',
                'Completada' => 'completed',
                'Incompleta' => 'incomplete',
                'Inactiva' => 'inactive'
            ];
            $status = $statusMap[$filtros['status']] ?? $filtros['status'];
            $conditions[] = "t.status = ?";
            $params[] = $status;
        }
        
        // Filtro por sucursal
        if (!empty($filtros['sucursal_id'])) {
            $conditions[] = "t.sucursal_id = ?";
            $params[] = $filtros['sucursal_id'];
        }
        
        // Filtro por categoría
        if (!empty($filtros['categoria_id'])) {
            $conditions[] = "t.categoria_id = ?";
            $params[] = $filtros['categoria_id'];
        }
        
        // Filtro por usuario asignado
        if (!empty($filtros['assigned_user_id'])) {
            $conditions[] = "t.assigned_user_id = ?";
            $params[] = $filtros['assigned_user_id'];
        }
        
        // Filtro tareas sin asignar: mostrar todas las tareas sin assigned_user_id
        if (!empty($filtros['sin_asignar'])) {
            $conditions[] = "t.assigned_user_id IS NULL";
        }
        
        // Agregar WHERE si hay condiciones
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Ordenamiento: prioridad (high primero), luego estado (completed al final), luego hora
        $sql .= " ORDER BY 
            FIELD(t.status, 'in_process', 'pending', 'incomplete', 'inactive', 'completed'),
            FIELD(t.priority, 'high', 'medium', 'low'),
            t.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar conteo real de subtareas
        foreach ($tareasAdmin as &$tarea) {
            $stats = $this->getEstadisticasSubtareas($tarea['id']);
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = (int)$stats['total'];
            $tarea['subtareasCompletadas'] = (int)$stats['completadas'];
        }
        
        return $tareasAdmin;
    }
    
    /**
     * Obtener una tarea por ID (formato admin legacy)
     */
    public function getTareaAdminPorId($tareaId) {
        $sql = "
            SELECT 
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
                t.deadline as fechaVencimiento,
                t.progreso,
                t.completed_at as fechaCompletado,
                t.completion_notes as observaciones,
                t.evidence_image as imagenes,
                t.motivo_reapertura as motivoReapertura,
                t.observaciones_reapertura as observacionesReapertura,
                ua.id as usuarioasignado_id,
                CONCAT(ua.nombre, ' ', ua.apellido) as usuarioasignado
            FROM tasks t
            LEFT JOIN categorias c ON t.categoria_id = c.id
            LEFT JOIN sucursales s ON t.sucursal_id = s.id
            LEFT JOIN users uc ON t.created_by_user_id = uc.id
            LEFT JOIN users ua ON t.assigned_user_id = ua.id
            WHERE t.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tareaId]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tarea) {
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = 0;
            $tarea['subtareasCompletadas'] = 0;
            
            // Obtener evidencias
            $tarea['evidencias'] = $this->getEvidencias($tareaId);
        }
        
        return $tarea;
    }
    
    /**
     * Obtener evidencias de una tarea
     */
    private function getEvidencias($taskId) {
        $sql = "
            SELECT 
                te.id,
                te.file_path,
                te.file_name,
                te.observaciones,
                te.uploaded_at,
                CONCAT(u.nombre, ' ', u.apellido) as uploaded_by
            FROM task_evidence te
            LEFT JOIN users u ON te.user_id = u.id
            WHERE te.task_id = ?
            ORDER BY te.uploaded_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nueva tarea
     */
    public function crearTareaAdmin($data, $userId = null) {
        // Buscar categoria_id por nombre si viene como string
        $categoriaId = null;
        if (!empty($data['Categoria'])) {
            $cat_stmt = $this->db->prepare('SELECT id FROM categorias WHERE nombre = ?');
            $cat_stmt->execute([$data['Categoria']]);
            $categoriaId = $cat_stmt->fetchColumn();
        } elseif (!empty($data['categoria_id'])) {
            $categoriaId = $data['categoria_id'];
        }
        
        // Buscar sucursal_id por nombre si viene como string
        $sucursalId = null;
        if (!empty($data['sucursal'])) {
            $suc_stmt = $this->db->prepare('SELECT id FROM sucursales WHERE nombre = ?');
            $suc_stmt->execute([$data['sucursal']]);
            $sucursalId = $suc_stmt->fetchColumn();
        } elseif (!empty($data['sucursal_id'])) {
            $sucursalId = $data['sucursal_id'];
        }
        
        // Mapear estado legacy a nuevo
        $statusMap = [
            'Pendiente' => 'pending',
            'En progreso' => 'in_process',
            'Completada' => 'completed',
            'Incompleta' => 'incomplete',
            'Inactiva' => 'inactive'
        ];
        $status = $statusMap[$data['estado'] ?? 'Pendiente'] ?? 'pending';
        
        // Mapear prioridad
        $priorityMap = [
            'Alta' => 'high',
            'Media' => 'medium',
            'Baja' => 'low'
        ];
        $priority = $priorityMap[$data['prioridad'] ?? 'Media'] ?? 'medium';
        
        $sql = "
            INSERT INTO tasks (
                title, description, categoria_id, status, priority,
                deadline, fecha_asignacion, horainicio, horafin,
                assigned_user_id, created_by_user_id, sucursal_id,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                NOW(), NOW()
            )
        ";
        
        $fechaAsignacion = $data['fechaAsignacion'] ?? date('Y-m-d');
        $deadline = $data['fechaVencimiento'] ?? date('Y-m-d', strtotime($fechaAsignacion . ' +2 days'));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['titulo'] ?? '',
            $data['descripcion'] ?? null,
            $categoriaId,
            $status,
            $priority,
            $deadline,
            $fechaAsignacion,
            $data['horainicio'] ?? null,
            $data['horafin'] ?? null,
            $data['usuarioasignado_id'] ?? null,
            $userId,
            $sucursalId
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualizar tarea
     */
    public function actualizarTareaAdmin($tareaId, $data) {
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
            'motivoReapertura' => 'motivo_reapertura',
            'observacionesReapertura' => 'observaciones_reapertura'
        ];
        
        foreach ($fieldMap as $legacyField => $dbField) {
            if (isset($data[$legacyField])) {
                $updateFields[] = "$dbField = ?";
                $params[] = $data[$legacyField];
            }
        }
        
        // Mapear estado
        if (isset($data['estado'])) {
            $statusMap = [
                'Pendiente' => 'pending',
                'En progreso' => 'in_process',
                'Completada' => 'completed',
                'Incompleta' => 'incomplete',
                'Inactiva' => 'inactive'
            ];
            $updateFields[] = "status = ?";
            $params[] = $statusMap[$data['estado']] ?? 'pending';
        }
        
        // Mapear prioridad
        if (isset($data['prioridad'])) {
            $priorityMap = [
                'Alta' => 'high',
                'Media' => 'medium',
                'Baja' => 'low'
            ];
            $updateFields[] = "priority = ?";
            $params[] = $priorityMap[$data['prioridad']] ?? 'medium';
        }
        
        // Manejar categoría por nombre
        if (isset($data['Categoria'])) {
            $cat_stmt = $this->db->prepare('SELECT id FROM categorias WHERE nombre = ?');
            $cat_stmt->execute([$data['Categoria']]);
            $catId = $cat_stmt->fetchColumn();
            if ($catId) {
                $updateFields[] = "categoria_id = ?";
                $params[] = $catId;
            }
        }
        
        // Manejar sucursal por nombre
        if (isset($data['sucursal'])) {
            $suc_stmt = $this->db->prepare('SELECT id FROM sucursales WHERE nombre = ?');
            $suc_stmt->execute([$data['sucursal']]);
            $sucId = $suc_stmt->fetchColumn();
            if ($sucId) {
                $updateFields[] = "sucursal_id = ?";
                $params[] = $sucId;
            }
        }
        
        if (empty($updateFields)) {
            return true;
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $tareaId;
        
        $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar tarea
     */
    public function eliminarTareaAdmin($tareaId) {
        // Primero eliminar evidencias
        $sqlEv = "DELETE FROM task_evidence WHERE task_id = ?";
        $stmtEv = $this->db->prepare($sqlEv);
        $stmtEv->execute([$tareaId]);
        
        // Eliminar historial
        $sqlHist = "DELETE FROM task_history WHERE task_id = ?";
        $stmtHist = $this->db->prepare($sqlHist);
        $stmtHist->execute([$tareaId]);
        
        // Eliminar tarea
        $sql = "DELETE FROM tasks WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$tareaId]);
    }
    
    /**
     * Obtener tareas por sucursal
     */
    public function getTareasAdminPorSucursal($sucursalId) {
        $sql = "
            SELECT 
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
                DATE_FORMAT(t.fecha_asignacion, '%Y-%m-%d') as fechaAsignacion, 
                t.horainicio as horaprogramada, 
                t.horainicio, 
                t.horafin, 
                s.nombre as sucursal,
                c.nombre as Categoria
            FROM tasks t
            LEFT JOIN categorias c ON t.categoria_id = c.id
            LEFT JOIN sucursales s ON t.sucursal_id = s.id
            WHERE t.sucursal_id = ?
            ORDER BY t.fecha_asignacion DESC, t.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sucursalId]);
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tareasAdmin as &$tarea) {
            $tarea['Tarea'] = [];
            $tarea['totalSubtareas'] = 0;
            $tarea['subtareasCompletadas'] = 0;
        }
        
        return $tareasAdmin;
    }

    /**
     * Auto-asignar tarea a un usuario
     */
    public function asignarTarea($tareaId, $userId) {
        $sql = "UPDATE tasks SET assigned_user_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $tareaId]);
    }

    /**
     * Iniciar tarea (cambiar estado a 'in_process')
     */
    public function iniciarTarea($tareaId) {
        $sql = "UPDATE tasks SET status = 'in_process', updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$tareaId]);
    }

    /**
     * Completar tarea con observaciones y evidencia
     */
    public function completarTarea($tareaId, $observaciones, $imagePath = null) {
        $sql = "
            UPDATE tasks SET 
                status = 'completed', 
                completion_notes = ?, 
                evidence_image = ?,
                completed_at = NOW(),
                progreso = 100,
                updated_at = NOW() 
            WHERE id = ?
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$observaciones, $imagePath, $tareaId]);
    }

    /**
     * Agregar evidencia a una tarea
     */
    public function agregarEvidencia($tareaId, $userId, $filePath, $fileName, $fileSize, $mimeType, $observaciones = null) {
        $sql = "
            INSERT INTO task_evidence (
                task_id, user_id, file_path, file_name, 
                file_size_kb, mime_type, observaciones, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $tareaId, $userId, $filePath, $fileName, 
            round($fileSize / 1024, 2), $mimeType, $observaciones
        ]);
    }

    /**
     * Reabrir tarea
     */
    public function reabrirTarea($tareaId, $motivo, $observaciones = null) {
        $sql = "
            UPDATE tasks SET 
                status = 'pending', 
                motivo_reapertura = ?,
                observaciones_reapertura = ?,
                fecha_reapertura = NOW(),
                completed_at = NULL,
                progreso = 0,
                updated_at = NOW() 
            WHERE id = ?
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$motivo, $observaciones, $tareaId]);
    }

    /**
     * Verificar si tarea puede ser asignada (del mismo día y sin asignar)
     */
    public function puedeSerAsignada($tareaId) {
        $sql = "
            SELECT COUNT(*) FROM tasks 
            WHERE id = ? 
            AND assigned_user_id IS NULL 
            AND DATE(fecha_asignacion) = CURDATE()
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tareaId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Marcar tareas vencidas como incompletas (para cron job)
     */
    public function marcarTareasIncompletas() {
        $sql = "
            UPDATE tasks 
            SET status = 'incomplete', updated_at = NOW() 
            WHERE status IN ('pending', 'in_process') 
            AND deadline < CURDATE()
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
    
    /**
     * Inactivar tareas vencidas automáticamente
     * Las tareas que no se completaron hasta el día después de la fecha_asignacion se inactivan
     * También tareas con deadline vencido
     */
    public function inactivarTareasVencidas() {
        // Inactivar tareas pendientes/en progreso cuya fecha de asignación + 1 día ya pasó
        // Es decir, si la tarea era para el 30/11, tiene hasta el 01/12 para completarla
        $sql = "
            UPDATE tasks 
            SET status = 'inactive', updated_at = NOW() 
            WHERE status IN ('pending', 'in_process') 
            AND (
                DATE_ADD(fecha_asignacion, INTERVAL 1 DAY) < CURDATE()
                OR (deadline IS NOT NULL AND DATE_ADD(deadline, INTERVAL 1 DAY) < CURDATE())
            )
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
}
