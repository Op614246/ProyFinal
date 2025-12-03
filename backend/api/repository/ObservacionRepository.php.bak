<?php
/**
 * ObservacionRepository.php
 * 
 * Repositorio para gestión de observaciones de tareas
 * Tabla: task_observaciones
 */

require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../core/Logger.php';

class ObservacionRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener todas las observaciones de una tarea
     */
    public function getByTaskId(int $taskId, ?string $tipo = null): array {
        $sql = "
            SELECT 
                tob.id,
                tob.task_id,
                tob.observacion,
                tob.created_by,
                tob.created_at,
                tob.tipo,
                CONCAT(u.nombre, ' ', u.apellido) as created_by_nombre
            FROM task_observaciones tob
            LEFT JOIN users u ON tob.created_by = u.id
            WHERE tob.task_id = ?
        ";
        
        $params = [$taskId];
        
        if ($tipo) {
            $sql .= " AND tob.tipo = ?";
            $params[] = $tipo;
        }
        
        $sql .= " ORDER BY tob.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener una observación por ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM task_observaciones WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Crear nueva observación
     * 
     * @param int $taskId ID de la tarea
     * @param string $observacion Texto de la observación
     * @param string $tipo Tipo: completado, reapertura, general
     * @param int|null $createdBy Usuario que crea
     * @return int ID de la observación creada
     */
    public function create(
        int $taskId, 
        string $observacion, 
        string $tipo = 'completado',
        ?int $createdBy = null
    ): int {
        $sql = "
            INSERT INTO task_observaciones 
            (task_id, observacion, tipo, created_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $taskId,
            $observacion,
            $tipo,
            $createdBy
        ]);
        
        $id = (int) $this->db->lastInsertId();
        
        Logger::info('Observación creada', [
            'observacion_id' => $id,
            'task_id' => $taskId,
            'tipo' => $tipo,
            'created_by' => $createdBy
        ]);
        
        return $id;
    }
    
    /**
     * Eliminar observación por ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM task_observaciones WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Obtener última observación de completado de una tarea
     */
    public function getLastCompletionNote(int $taskId): ?string {
        $sql = "
            SELECT observacion FROM task_observaciones 
            WHERE task_id = ? AND tipo = 'completado'
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }
}
