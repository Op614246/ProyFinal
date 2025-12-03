<?php
/**
 * SubtareaRepository.php
 * 
 * Repositorio para gestión de subtareas vinculadas a tasks
 */

class SubtareaRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener todas las subtareas de una tarea
     */
    public function getSubtareasByTaskId($taskId) {
        $sql = "
            SELECT 
                s.id,
                s.task_id,
                s.titulo,
                s.descripcion,
                s.estado,
                s.prioridad,
                s.completada,
                s.progreso,
                DATE_FORMAT(s.fechaAsignacion, '%Y-%m-%d') as fechaAsignacion,
                DATE_FORMAT(s.fechaVencimiento, '%Y-%m-%d') as fechaVencimiento,
                s.horainicio,
                s.horafin,
                s.categoria_id,
                c.nombre as categoria_nombre,
                c.color as categoria_color,
                s.usuarioasignado_id,
                CONCAT(u.nombre, ' ', u.apellido) as usuario_asignado,
                u.username as usuario_username,
                s.completed_at,
                s.completed_by,
                s.completion_notes,
                s.created_at,
                s.updated_at,
                (SELECT COUNT(*) FROM subtarea_evidencias WHERE subtarea_id = s.id) as evidencia_count
            FROM subtareas s
            LEFT JOIN categorias c ON s.categoria_id = c.id
            LEFT JOIN users u ON s.usuarioasignado_id = u.id
            WHERE s.task_id = ?
            ORDER BY 
                FIELD(s.estado, 'En progreso', 'Pendiente', 'Completada', 'Cerrada'),
                FIELD(s.prioridad, 'Alta', 'Media', 'Baja'),
                s.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener una subtarea por ID
     */
    public function getSubtareaById($subtareaId) {
        $sql = "
            SELECT 
                s.id,
                s.task_id,
                s.titulo,
                s.descripcion,
                s.estado,
                s.prioridad,
                s.completada,
                s.progreso,
                DATE_FORMAT(s.fechaAsignacion, '%Y-%m-%d') as fechaAsignacion,
                DATE_FORMAT(s.fechaVencimiento, '%Y-%m-%d') as fechaVencimiento,
                s.horainicio,
                s.horafin,
                s.categoria_id,
                c.nombre as categoria_nombre,
                c.color as categoria_color,
                s.usuarioasignado_id,
                CONCAT(u.nombre, ' ', u.apellido) as usuario_asignado,
                u.username as usuario_username,
                s.completed_at,
                s.completed_by,
                CONCAT(uc.nombre, ' ', uc.apellido) as completed_by_nombre,
                s.completion_notes,
                s.created_at,
                s.updated_at,
                (SELECT COUNT(*) FROM subtarea_evidencias WHERE subtarea_id = s.id) as evidencia_count
            FROM subtareas s
            LEFT JOIN categorias c ON s.categoria_id = c.id
            LEFT JOIN users u ON s.usuarioasignado_id = u.id
            LEFT JOIN users uc ON s.completed_by = uc.id
            WHERE s.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId]);
        $subtarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Incluir evidencias si existen
        if ($subtarea && $subtarea['evidencia_count'] > 0) {
            $subtarea['evidencias'] = $this->getEvidenciasBySubtarea($subtareaId);
        }
        
        return $subtarea;
    }
    
    /**
     * Crear nueva subtarea
     */
    public function crearSubtarea($data) {
        $sql = "
            INSERT INTO subtareas (
                task_id, titulo, descripcion, estado, prioridad,
                fechaAsignacion, fechaVencimiento, horainicio, horafin,
                categoria_id, usuarioasignado_id, progreso, completada
            ) VALUES (
                :task_id, :titulo, :descripcion, :estado, :prioridad,
                :fechaAsignacion, :fechaVencimiento, :horainicio, :horafin,
                :categoria_id, :usuarioasignado_id, 0, 0
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':task_id' => $data['task_id'],
            ':titulo' => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':estado' => $data['estado'] ?? 'Pendiente',
            ':prioridad' => $data['prioridad'] ?? 'Media',
            ':fechaAsignacion' => $data['fechaAsignacion'] ?? date('Y-m-d'),
            ':fechaVencimiento' => $data['fechaVencimiento'] ?? null,
            ':horainicio' => $data['horainicio'] ?? null,
            ':horafin' => $data['horafin'] ?? null,
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':usuarioasignado_id' => $data['usuarioasignado_id'] ?? null
        ]);

        $subtareaId = $this->db->lastInsertId();

        // Actualizar progreso de la tarea padre
        $this->actualizarProgresoTarea($data['task_id']);

        return $subtareaId;
    }
    
    /**
     * Actualizar subtarea
     */
    public function actualizarSubtarea($subtareaId, $data) {
        $campos = [];
        $params = [':id' => $subtareaId];
        
        $camposPermitidos = [
            'titulo', 'descripcion', 'estado', 'prioridad',
            'fechaAsignacion', 'fechaVencimiento', 'horainicio', 'horafin',
            'categoria_id', 'usuarioasignado_id', 'progreso'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $data[$campo];
            }
        }
        
        // Si el estado es 'Completada', marcar completada = 1 y progreso = 100
        if (isset($data['estado']) && $data['estado'] === 'Completada') {
            $campos[] = "completada = 1";
            $campos[] = "progreso = 100";
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $sql = "UPDATE subtareas SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        // Obtener task_id para actualizar progreso
        $subtarea = $this->getSubtareaById($subtareaId);
        if ($subtarea) {
            $this->actualizarProgresoTarea($subtarea['task_id']);
        }
        
        return $result;
    }
    
    /**
     * Eliminar subtarea
     */
    public function eliminarSubtarea($subtareaId) {
        // Primero obtener task_id
        $subtarea = $this->getSubtareaById($subtareaId);
        $taskId = $subtarea ? $subtarea['task_id'] : null;
        
        $sql = "DELETE FROM subtareas WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$subtareaId]);
        
        // Actualizar progreso de la tarea padre
        if ($taskId) {
            $this->actualizarProgresoTarea($taskId);
        }
        
        return $result;
    }
    
    /**
     * Completar subtarea
     * @param int $subtareaId ID de la subtarea
     * @param int|null $userId ID del usuario que completa
     * @param string|null $observaciones Observaciones de completado
     */
    public function completarSubtarea($subtareaId, $userId = null, $observaciones = null) {
        $sql = "
            UPDATE subtareas 
            SET estado = 'Completada', 
                completada = 1, 
                progreso = 100,
                completed_at = NOW(),
                completed_by = ?,
                completion_notes = ?
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$userId, $observaciones, $subtareaId]);
        
        // Obtener task_id para actualizar progreso
        $subtarea = $this->getSubtareaById($subtareaId);
        if ($subtarea) {
            $this->actualizarProgresoTarea($subtarea['task_id']);
        }
        
        return $result;
    }
    
    /**
     * Iniciar subtarea (cambiar a En progreso)
     */
    public function iniciarSubtarea($subtareaId) {
        $sql = "UPDATE subtareas SET estado = 'En progreso' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$subtareaId]);
    }
    
    /**
     * Calcular y actualizar el progreso de una tarea basado en sus subtareas
     */
    public function actualizarProgresoTarea($taskId) {
        // Calcular progreso basado en subtareas completadas
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END) as completadas
            FROM subtareas 
            WHERE task_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $progreso = 0;
        if ($stats['total'] > 0) {
            $progreso = round(($stats['completadas'] / $stats['total']) * 100);
        }
        
        // Actualizar progreso en la tarea
        $sqlUpdate = "UPDATE tasks SET progreso = ? WHERE id = ?";
        $stmtUpdate = $this->db->prepare($sqlUpdate);
        $stmtUpdate->execute([$progreso, $taskId]);
        
        // Si todas las subtareas están completadas, marcar tarea como completada
        if ($stats['total'] > 0 && $stats['completadas'] == $stats['total']) {
            $sqlComplete = "
                UPDATE tasks 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ? AND status != 'completed'
            ";
            $stmtComplete = $this->db->prepare($sqlComplete);
            $stmtComplete->execute([$taskId]);
        }
        
        return $progreso;
    }
    
    /**
     * Obtener estadísticas de subtareas de una tarea
     */
    public function getEstadisticasSubtareas($taskId) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'En progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN estado = 'Completada' OR completada = 1 THEN 1 ELSE 0 END) as completadas
            FROM subtareas 
            WHERE task_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Asignar subtarea a usuario
     */
    public function asignarSubtarea($subtareaId, $usuarioId) {
        $sql = "UPDATE subtareas SET usuarioasignado_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$usuarioId, $subtareaId]);
    }
    
    /**
     * Obtener subtareas asignadas a un usuario
     */
    public function getSubtareasByUsuario($usuarioId, $fecha = null) {
        $sql = "
            SELECT 
                s.id,
                s.task_id,
                s.titulo,
                s.descripcion,
                s.estado,
                s.prioridad,
                s.completada,
                s.progreso,
                DATE_FORMAT(s.fechaAsignacion, '%Y-%m-%d') as fechaAsignacion,
                DATE_FORMAT(s.fechaVencimiento, '%Y-%m-%d') as fechaVencimiento,
                s.horainicio,
                s.horafin,
                t.title as tarea_titulo,
                c.nombre as categoria_nombre,
                c.color as categoria_color
            FROM subtareas s
            INNER JOIN tasks t ON s.task_id = t.id
            LEFT JOIN categorias c ON s.categoria_id = c.id
            WHERE s.usuarioasignado_id = ?
        ";
        
        $params = [$usuarioId];
        
        if ($fecha) {
            $sql .= " AND DATE(s.fechaAsignacion) = ?";
            $params[] = $fecha;
        }
        
        $sql .= " ORDER BY 
            FIELD(s.estado, 'En progreso', 'Pendiente', 'Completada'),
            FIELD(s.prioridad, 'Alta', 'Media', 'Baja'),
            s.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // SECCIÓN: EVIDENCIAS DE SUBTAREAS
    // ============================================================

    /**
     * Completar subtarea con evidencia (imagen)
     * 
     * @param int $subtareaId ID de la subtarea
     * @param int $userId ID del usuario que completa
     * @param string $archivoPath Ruta del archivo de evidencia
     * @param string|null $observaciones Notas de completado
     * @param array|null $fileInfo Información adicional del archivo
     * @return bool
     */
    public function completarSubtareaConEvidencia(
        int $subtareaId, 
        int $userId, 
        string $archivoPath, 
        ?string $observaciones = null,
        ?array $fileInfo = null
    ): bool {
        try {
            $this->db->beginTransaction();

            // 1. Actualizar subtarea como completada
            $sqlSubtarea = "
                UPDATE subtareas 
                SET estado = 'Completada', 
                    completada = 1, 
                    progreso = 100,
                    completed_at = NOW(),
                    completed_by = ?,
                    completion_notes = ?
                WHERE id = ?
            ";
            $stmtSubtarea = $this->db->prepare($sqlSubtarea);
            $stmtSubtarea->execute([$userId, $observaciones, $subtareaId]);

            // 2. Insertar evidencia
            $sqlEvidencia = "
                INSERT INTO subtarea_evidencias 
                (subtarea_id, archivo, tipo, nombre_original, tamanio, uploaded_by, observaciones, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmtEvidencia = $this->db->prepare($sqlEvidencia);
            $stmtEvidencia->execute([
                $subtareaId,
                $archivoPath,
                $fileInfo['tipo'] ?? 'imagen',
                $fileInfo['nombre_original'] ?? null,
                $fileInfo['tamanio'] ?? null,
                $userId,
                $observaciones
            ]);

            // 3. Actualizar progreso de la tarea padre
            $subtarea = $this->getSubtareaById($subtareaId);
            if ($subtarea) {
                $this->actualizarProgresoTarea($subtarea['task_id']);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtener evidencias de una subtarea
     * 
     * @param int $subtareaId ID de la subtarea
     * @return array Lista de evidencias
     */
    public function getEvidenciasBySubtarea(int $subtareaId): array
    {
        $sql = "
            SELECT 
                e.id,
                e.subtarea_id,
                e.archivo,
                e.tipo,
                e.nombre_original,
                e.tamanio,
                e.uploaded_by,
                CONCAT(u.nombre, ' ', u.apellido) as uploaded_by_nombre,
                e.observaciones,
                e.created_at
            FROM subtarea_evidencias e
            LEFT JOIN users u ON e.uploaded_by = u.id
            WHERE e.subtarea_id = ?
            ORDER BY e.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregar evidencia adicional a una subtarea
     * 
     * @param int $subtareaId ID de la subtarea
     * @param int $userId ID del usuario que sube
     * @param string $archivoPath Ruta del archivo
     * @param array|null $fileInfo Información del archivo
     * @param string|null $observaciones Notas
     * @return int|false ID de la evidencia creada o false
     */
    public function agregarEvidencia(
        int $subtareaId,
        int $userId,
        string $archivoPath,
        ?array $fileInfo = null,
        ?string $observaciones = null
    ) {
        $sql = "
            INSERT INTO subtarea_evidencias 
            (subtarea_id, archivo, tipo, nombre_original, tamanio, uploaded_by, observaciones, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $subtareaId,
            $archivoPath,
            $fileInfo['tipo'] ?? 'imagen',
            $fileInfo['nombre_original'] ?? null,
            $fileInfo['tamanio'] ?? null,
            $userId,
            $observaciones
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Eliminar evidencia
     * 
     * @param int $evidenciaId ID de la evidencia
     * @return bool
     */
    public function eliminarEvidencia(int $evidenciaId): bool
    {
        // Primero obtener la ruta del archivo para poder eliminarlo del sistema de archivos
        $sql = "SELECT archivo FROM subtarea_evidencias WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$evidenciaId]);
        $evidencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evidencia) {
            return false;
        }

        // Eliminar de la BD
        $sqlDelete = "DELETE FROM subtarea_evidencias WHERE id = ?";
        $stmtDelete = $this->db->prepare($sqlDelete);
        return $stmtDelete->execute([$evidenciaId]);
    }

    /**
     * Obtener evidencia por ID
     * 
     * @param int $evidenciaId ID de la evidencia
     * @return array|false
     */
    public function getEvidenciaById(int $evidenciaId)
    {
        $sql = "
            SELECT 
                e.*,
                CONCAT(u.nombre, ' ', u.apellido) as uploaded_by_nombre
            FROM subtarea_evidencias e
            LEFT JOIN users u ON e.uploaded_by = u.id
            WHERE e.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$evidenciaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Contar evidencias de una subtarea
     * 
     * @param int $subtareaId ID de la subtarea
     * @return int
     */
    public function countEvidencias(int $subtareaId): int
    {
        $sql = "SELECT COUNT(*) FROM subtarea_evidencias WHERE subtarea_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verificar si la subtarea pertenece al usuario (para validar permisos)
     * 
     * @param int $subtareaId ID de la subtarea
     * @param int $userId ID del usuario
     * @return bool
     */
    public function isSubtareaAsignadaAUsuario(int $subtareaId, int $userId): bool
    {
        $sql = "SELECT 1 FROM subtareas WHERE id = ? AND usuarioasignado_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Verificar si una subtarea puede ser completada
     * 
     * @param int $subtareaId ID de la subtarea
     * @return array ['can_complete' => bool, 'reason' => string|null]
     */
    public function canCompleteSubtarea(int $subtareaId): array
    {
        $subtarea = $this->getSubtareaById($subtareaId);

        if (!$subtarea) {
            return ['can_complete' => false, 'reason' => 'Subtarea no encontrada'];
        }

        if ($subtarea['completada'] == 1) {
            return ['can_complete' => false, 'reason' => 'La subtarea ya está completada'];
        }

        $estadosCompletables = ['Pendiente', 'En progreso'];
        if (!in_array($subtarea['estado'], $estadosCompletables)) {
            return ['can_complete' => false, 'reason' => 'El estado actual no permite completar'];
        }

        return ['can_complete' => true, 'reason' => null];
    }
}

