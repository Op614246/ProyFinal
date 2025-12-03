<?php
/**
 * EvidenciaRepository.php
 * 
 * Repositorio para gestión de evidencias de tareas
 * Tabla: task_evidencias
 */

require_once __DIR__ . '/../core/DB.php';
require_once __DIR__ . '/../core/Logger.php';

class EvidenciaRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener todas las evidencias de una tarea
     */
    public function getByTaskId(int $taskId): array {
        $sql = "
            SELECT 
                te.id,
                te.task_id,
                te.archivo,
                te.tipo,
                te.nombre_original,
                te.tamanio,
                te.uploaded_by,
                te.created_at,
                CONCAT(u.nombre, ' ', u.apellido) as uploaded_by_nombre
            FROM task_evidencias te
            LEFT JOIN users u ON te.uploaded_by = u.id
            WHERE te.task_id = ?
            ORDER BY te.created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener una evidencia por ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM task_evidencias WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Crear nueva evidencia
     * 
     * @param int $taskId ID de la tarea
     * @param string $archivo Ruta del archivo
     * @param int|null $uploadedBy Usuario que sube
     * @param string $tipo Tipo de archivo (imagen, documento, otro)
     * @param string|null $nombreOriginal Nombre original del archivo
     * @param int|null $tamanio Tamaño en bytes
     * @return int ID de la evidencia creada
     */
    public function create(
        int $taskId, 
        string $archivo, 
        ?int $uploadedBy = null,
        string $tipo = 'imagen',
        ?string $nombreOriginal = null,
        ?int $tamanio = null
    ): int {
        $sql = "
            INSERT INTO task_evidencias 
            (task_id, archivo, tipo, nombre_original, tamanio, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $taskId,
            $archivo,
            $tipo,
            $nombreOriginal,
            $tamanio,
            $uploadedBy
        ]);
        
        $id = (int) $this->db->lastInsertId();
        
        Logger::info('Evidencia creada', [
            'evidencia_id' => $id,
            'task_id' => $taskId,
            'archivo' => $archivo,
            'uploaded_by' => $uploadedBy
        ]);
        
        return $id;
    }
    
    /**
     * Crear múltiples evidencias para una tarea
     * 
     * @param int $taskId ID de la tarea
     * @param array $archivos Array de rutas de archivos
     * @param int|null $uploadedBy Usuario que sube
     * @return array IDs de las evidencias creadas
     */
    public function createMultiple(int $taskId, array $archivos, ?int $uploadedBy = null): array {
        $ids = [];
        foreach ($archivos as $archivo) {
            if (is_array($archivo)) {
                $ids[] = $this->create(
                    $taskId,
                    $archivo['path'] ?? $archivo['archivo'] ?? $archivo['url'] ?? '',
                    $uploadedBy,
                    $archivo['tipo'] ?? 'imagen',
                    $archivo['nombre_original'] ?? null,
                    $archivo['tamanio'] ?? null
                );
            } else {
                $ids[] = $this->create($taskId, $archivo, $uploadedBy);
            }
        }
        return $ids;
    }
    
    /**
     * Eliminar evidencia por ID
     */
    public function delete(int $id): bool {
        $evidencia = $this->getById($id);
        
        $sql = "DELETE FROM task_evidencias WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$id]);
        
        if ($result && $evidencia) {
            Logger::info('Evidencia eliminada', [
                'evidencia_id' => $id,
                'task_id' => $evidencia['task_id'],
                'archivo' => $evidencia['archivo']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Eliminar todas las evidencias de una tarea
     */
    public function deleteByTaskId(int $taskId): bool {
        $sql = "DELETE FROM task_evidencias WHERE task_id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$taskId]);
        
        Logger::info('Evidencias de tarea eliminadas', ['task_id' => $taskId]);
        
        return $result;
    }
    
    /**
     * Contar evidencias de una tarea
     */
    public function countByTaskId(int $taskId): int {
        $sql = "SELECT COUNT(*) FROM task_evidencias WHERE task_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }
}
