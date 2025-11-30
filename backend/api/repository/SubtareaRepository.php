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
                s.created_at,
                s.updated_at
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
                s.usuarioasignado_id,
                CONCAT(u.nombre, ' ', u.apellido) as usuario_asignado,
                s.created_at,
                s.updated_at
            FROM subtareas s
            LEFT JOIN categorias c ON s.categoria_id = c.id
            LEFT JOIN users u ON s.usuarioasignado_id = u.id
            WHERE s.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
     */
    public function completarSubtarea($subtareaId, $observaciones = null) {
        $sql = "
            UPDATE subtareas 
            SET estado = 'Completada', 
                completada = 1, 
                progreso = 100
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$subtareaId]);
        
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
}
