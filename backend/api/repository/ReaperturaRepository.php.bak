<?php
/**
 * ReaperturaRepository.php
 * 
 * Repositorio para gestión del historial de reaperturas de tareas
 * Tabla: task_reaperturas
 */

require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../core/Logger.php';

class ReaperturaRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener historial de reaperturas de una tarea
     */
    public function getByTaskId(int $taskId): array {
        $sql = "
            SELECT 
                tr.id,
                tr.task_id,
                tr.reopened_by,
                tr.reopened_at,
                tr.motivo,
                tr.observaciones,
                tr.previous_status,
                tr.previous_assigned_user_id,
                tr.previous_deadline,
                tr.previous_priority,
                tr.previous_completed_at,
                tr.new_assigned_user_id,
                tr.new_deadline,
                tr.new_priority,
                CONCAT(ur.nombre, ' ', ur.apellido) as reopened_by_nombre,
                CONCAT(up.nombre, ' ', up.apellido) as previous_assigned_nombre,
                CONCAT(un.nombre, ' ', un.apellido) as new_assigned_nombre
            FROM task_reaperturas tr
            LEFT JOIN users ur ON tr.reopened_by = ur.id
            LEFT JOIN users up ON tr.previous_assigned_user_id = up.id
            LEFT JOIN users un ON tr.new_assigned_user_id = un.id
            WHERE tr.task_id = ?
            ORDER BY tr.reopened_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener una reapertura por ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM task_reaperturas WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Registrar una nueva reapertura
     * 
     * @param int $taskId ID de la tarea
     * @param string $motivo Motivo de la reapertura
     * @param array $previousState Estado anterior de la tarea
     * @param array $newState Nuevo estado asignado
     * @param int|null $reopenedBy Usuario que reabre
     * @param string|null $observaciones Observaciones adicionales
     * @return int ID de la reapertura creada
     */
    public function create(
        int $taskId,
        string $motivo,
        array $previousState = [],
        array $newState = [],
        ?int $reopenedBy = null,
        ?string $observaciones = null
    ): int {
        $sql = "
            INSERT INTO task_reaperturas (
                task_id, motivo, observaciones, reopened_by, reopened_at,
                previous_status, previous_assigned_user_id, previous_deadline, 
                previous_priority, previous_completed_at,
                new_assigned_user_id, new_deadline, new_priority
            ) VALUES (
                ?, ?, ?, ?, NOW(),
                ?, ?, ?, ?, ?,
                ?, ?, ?
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $taskId,
            $motivo,
            $observaciones,
            $reopenedBy,
            $previousState['status'] ?? null,
            $previousState['assigned_user_id'] ?? null,
            $previousState['deadline'] ?? null,
            $previousState['priority'] ?? null,
            $previousState['completed_at'] ?? null,
            $newState['assigned_user_id'] ?? null,
            $newState['deadline'] ?? null,
            $newState['priority'] ?? null
        ]);
        
        $id = (int) $this->db->lastInsertId();
        
        Logger::info('Reapertura registrada', [
            'reapertura_id' => $id,
            'task_id' => $taskId,
            'motivo' => $motivo,
            'reopened_by' => $reopenedBy
        ]);
        
        return $id;
    }
    
    /**
     * Contar reaperturas de una tarea
     */
    public function countByTaskId(int $taskId): int {
        $sql = "SELECT COUNT(*) FROM task_reaperturas WHERE task_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Obtener última reapertura de una tarea
     */
    public function getLastByTaskId(int $taskId): ?array {
        $sql = "
            SELECT * FROM task_reaperturas 
            WHERE task_id = ? 
            ORDER BY reopened_at DESC 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Obtener reaperturas recientes (últimas N)
     */
    public function getRecent(int $limit = 10): array {
        $sql = "
            SELECT 
                tr.*,
                t.title as task_title,
                CONCAT(ur.nombre, ' ', ur.apellido) as reopened_by_nombre
            FROM task_reaperturas tr
            JOIN tasks t ON tr.task_id = t.id
            LEFT JOIN users ur ON tr.reopened_by = ur.id
            ORDER BY tr.reopened_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
