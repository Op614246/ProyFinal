<?php
class SubtareaRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    public function getSubtareasByTaskId($taskId) {
        $sql = "
            SELECT 
                s.id,
                s.task_id,
                s.titulo,
                s.descripcion,
                s.estado,
                s.prioridad,
                s.progreso,
                s.completed_by,
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
            WHERE s.task_id = ? AND s.is_deleted = 0
            ORDER BY 
                FIELD(s.estado, 'En progreso', 'Pendiente', 'Completada', 'Cerrada'),
                FIELD(s.prioridad, 'Alta', 'Media', 'Baja'),
                s.created_at ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSubtareaById($subtareaId) {
        $sql = "
            SELECT 
                s.id,
                s.task_id,
                s.titulo,
                s.descripcion,
                s.estado,
                s.prioridad,
                s.progreso,
                s.completed_by,
                s.categoria_id,
                c.nombre as categoria_nombre,
                s.usuarioasignado_id,
                CONCAT(u.nombre, ' ', u.apellido) as usuario_asignado,
                s.created_at,
                s.updated_at
            FROM subtareas s
            LEFT JOIN categorias c ON s.categoria_id = c.id
            LEFT JOIN users u ON s.usuarioasignado_id = u.id
            WHERE s.id = ? AND s.is_deleted = 0
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subtareaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function crearSubtarea($data) {
        $sql = "
            INSERT INTO subtareas (
                task_id, titulo, descripcion, estado, prioridad,
                categoria_id, usuarioasignado_id, progreso
            ) VALUES (
                :task_id, :titulo, :descripcion, :estado, :prioridad,
                :categoria_id, :usuarioasignado_id, 0
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':task_id' => $data['task_id'],
            ':titulo' => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':estado' => $data['estado'] ?? 'Pendiente',
            ':prioridad' => $data['prioridad'] ?? 'Media',
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':usuarioasignado_id' => $data['usuarioasignado_id'] ?? null
        ]);

        $subtareaId = $this->db->lastInsertId();
        $this->actualizarProgresoTarea($data['task_id']);
        return $subtareaId;
    }
    
    public function actualizarSubtarea($subtareaId, $data) {
        $campos = [];
        $params = [':id' => $subtareaId];
        
        $camposPermitidos = [
            'titulo', 'descripcion', 'estado', 'prioridad',
            'categoria_id', 'usuarioasignado_id', 'progreso', 'completed_by'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $data)) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $data[$campo];
            }
        }
        
        if (isset($data['estado']) && $data['estado'] === 'Completada') {
            $campos[] = "progreso = 100";
        }
        
        if (empty($campos)) {
            return false;
        }
        
        $sql = "UPDATE subtareas SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        $subtarea = $this->getSubtareaById($subtareaId);
        if ($subtarea) {
            $this->actualizarProgresoTarea($subtarea['task_id']);
        }
        
        return $result;
    }
    
    public function eliminarSubtarea($subtareaId) {
        $subtarea = $this->getSubtareaById($subtareaId);
        $taskId = $subtarea ? $subtarea['task_id'] : null;
        
        $sql = "DELETE FROM subtareas WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$subtareaId]);
        
        if ($taskId) {
            $this->actualizarProgresoTarea($taskId);
        }
        
        return $result;
    }
    
    public function completarSubtarea($subtareaId, $observaciones = null) {
        $sql = "UPDATE subtareas SET estado = 'Completada', progreso = 100 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$subtareaId]);
        
        $subtarea = $this->getSubtareaById($subtareaId);
        if ($subtarea) {
            $this->actualizarProgresoTarea($subtarea['task_id']);
        }
        
        return $result;
    }
    
    public function iniciarSubtarea($subtareaId) {
        $sql = "UPDATE subtareas SET estado = 'En progreso' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$subtareaId]);
    }
    
    public function actualizarProgresoTarea($taskId) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) as completadas
            FROM subtareas 
            WHERE task_id = ? AND is_deleted = 0
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $progreso = 0;
        if ($stats['total'] > 0) {
            $progreso = round(($stats['completadas'] / $stats['total']) * 100);
        }
        
        $sqlUpdate = "UPDATE tasks SET progreso = ? WHERE id = ?";
        $stmtUpdate = $this->db->prepare($sqlUpdate);
        $stmtUpdate->execute([$progreso, $taskId]);
        
        if ($stats['total'] > 0 && $stats['completadas'] == $stats['total']) {
            $sqlComplete = "UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE id = ? AND status != 'completed'";
            $stmtComplete = $this->db->prepare($sqlComplete);
            $stmtComplete->execute([$taskId]);
        }
        
        return $progreso;
    }
    
    public function getEstadisticasSubtareas($taskId) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'En progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) as completadas
            FROM subtareas 
            WHERE task_id = ? AND is_deleted = 0
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function asignarSubtarea($subtareaId, $usuarioId) {
        $sql = "UPDATE subtareas SET usuarioasignado_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$usuarioId, $subtareaId]);
    }
    
    public function getSubtareasByUsuario($usuarioId, $fecha = null) {
        $sql = "
            SELECT 
                s.id, s.task_id, s.titulo, s.descripcion, s.estado, s.prioridad, s.progreso,
                s.completed_by,
                t.title as tarea_titulo,
                c.nombre as categoria_nombre, c.color as categoria_color
            FROM subtareas s
            INNER JOIN tasks t ON s.task_id = t.id
            LEFT JOIN categorias c ON s.categoria_id = c.id
            WHERE s.usuarioasignado_id = ? AND s.is_deleted = 0
        ";
        
        $params = [$usuarioId];
        
        if ($fecha) {
            $sql .= " AND DATE(s.created_at) = ?";
            $params[] = $fecha;
        }
        
        $sql .= " ORDER BY FIELD(s.estado, 'En progreso', 'Pendiente', 'Completada'), FIELD(s.prioridad, 'Alta', 'Media', 'Baja'), s.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
