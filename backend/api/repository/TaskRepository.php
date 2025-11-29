<?php
/**
 * TaskRepository.php
 * 
 * Repositorio para operaciones de tareas en la base de datos.
 * SQL puro optimizado para el módulo de tareas.
 */

class TaskRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    // ============================================================
    // SECCIÓN: CONSULTAS DE LECTURA
    // ============================================================

    /**
     * Obtiene todas las tareas con filtros para Admin
     * 
     * @param array $filters Filtros opcionales ['fecha_inicio', 'fecha_fin', 'status', 'priority']
     * @return array Lista de tareas
     */
    public function getAllForAdmin(array $filters = []): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.assigned_user_id,
                    u.username AS assigned_username,
                    t.completed_at,
                    t.evidence_image,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN users u ON t.assigned_user_id = u.id
                WHERE 1=1";

        $params = [];

        // Filtro por fecha de creación (inicio)
        if (!empty($filters['fecha_inicio'])) {
            $sql .= " AND DATE(t.created_at) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filters['fecha_inicio'];
        }

        // Filtro por fecha de creación (fin)
        if (!empty($filters['fecha_fin'])) {
            $sql .= " AND DATE(t.created_at) <= :fecha_fin";
            $params[':fecha_fin'] = $filters['fecha_fin'];
        }

        // Filtro por estado
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtro por prioridad
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene tareas para un usuario con ordenamiento específico:
     * - Prioridad: high > medium > low
     * - Estado: pending/in_process primero, completed al final
     * - Deadline: más próximo primero
     * 
     * @param int $userId ID del usuario
     * @return array Lista de tareas ordenadas
     */
    public function getAllForUser(int $userId): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.assigned_user_id,
                    t.completed_at,
                    t.evidence_image,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                WHERE t.assigned_user_id = :user_id
                ORDER BY 
                    -- Prioridad: high=1, medium=2, low=3
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    -- Estado: completed al final
                    CASE 
                        WHEN t.status = 'completed' THEN 2
                        WHEN t.status = 'closed' THEN 3
                        ELSE 1
                    END,
                    -- Deadline más próximo primero (NULL al final)
                    CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END,
                    t.deadline ASC,
                    t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene tareas disponibles (sin asignar) para usuarios
     * 
     * @return array Lista de tareas sin asignar
     */
    public function getAvailableTasks(): array
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.created_at
                FROM tasks t
                WHERE t.assigned_user_id IS NULL
                  AND t.status IN ('pending', 'in_process')
                ORDER BY 
                    FIELD(t.priority, 'high', 'medium', 'low'),
                    CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END,
                    t.deadline ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una tarea por ID
     * 
     * @param int $taskId ID de la tarea
     * @return array|false Datos de la tarea o false si no existe
     */
    public function getById(int $taskId)
    {
        $sql = "SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.status,
                    t.priority,
                    t.deadline,
                    t.assigned_user_id,
                    u.username AS assigned_username,
                    t.completed_at,
                    t.evidence_image,
                    t.created_at,
                    t.updated_at
                FROM tasks t
                LEFT JOIN users u ON t.assigned_user_id = u.id
                WHERE t.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // SECCIÓN: OPERACIONES DE ESCRITURA
    // ============================================================

    /**
     * Crea una nueva tarea
     * 
     * @param array $data Datos de la tarea
     * @return int|false ID de la tarea creada o false si falla
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO tasks (
                    title,
                    description,
                    status,
                    priority,
                    deadline,
                    assigned_user_id,
                    created_at,
                    updated_at
                ) VALUES (
                    :title,
                    :description,
                    :status,
                    :priority,
                    :deadline,
                    :assigned_user_id,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'],
            ':priority' => $data['priority'],
            ':deadline' => $data['deadline'] ?? null,
            ':assigned_user_id' => $data['assigned_user_id'] ?? null
        ]);

        if ($result) {
            return (int)$this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Asigna una tarea a un usuario
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @return bool True si se asignó, false si falla
     */
    public function assign(int $taskId, int $userId): bool
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
        return $stmt->execute([
            ':user_id' => $userId,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Completa una tarea con evidencia
     * 
     * @param int $taskId ID de la tarea
     * @param string $evidencePath Ruta de la imagen de evidencia
     * @return bool True si se completó, false si falla
     */
    public function complete(int $taskId, string $evidencePath): bool
    {
        $sql = "UPDATE tasks 
                SET status = 'completed',
                    evidence_image = :evidence_image,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':evidence_image' => $evidencePath,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Actualiza el estado de una tarea
     * 
     * @param int $taskId ID de la tarea
     * @param string $status Nuevo estado
     * @return bool True si se actualizó, false si falla
     */
    public function updateStatus(int $taskId, string $status): bool
    {
        $sql = "UPDATE tasks 
                SET status = :status,
                    updated_at = NOW()
                WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':task_id' => $taskId
        ]);
    }

    /**
     * Elimina una tarea
     * 
     * @param int $taskId ID de la tarea
     * @return bool True si se eliminó, false si falla
     */
    public function delete(int $taskId): bool
    {
        $sql = "DELETE FROM tasks WHERE id = :task_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':task_id' => $taskId]);
    }

    // ============================================================
    // SECCIÓN: VERIFICACIONES
    // ============================================================

    /**
     * Verifica si una tarea existe
     * 
     * @param int $taskId ID de la tarea
     * @return bool True si existe, false si no
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
     * 
     * @param int $taskId ID de la tarea
     * @param int $userId ID del usuario
     * @return bool True si está asignada al usuario, false si no
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
     * 
     * @param int $taskId ID de la tarea
     * @return bool True si está disponible, false si ya está asignada
     */
    public function isAvailable(int $taskId): bool
    {
        $sql = "SELECT assigned_user_id FROM tasks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['assigned_user_id'] === null;
    }

    /**
     * Obtiene el nombre de usuario por ID
     * 
     * @param int $userId ID del usuario
     * @return string|null Username o null si no existe
     */
    public function getUsernameById(int $userId): ?string
    {
        $sql = "SELECT username FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['username'] : null;
    }

    /**
     * Verifica si un usuario existe
     * 
     * @param int $userId ID del usuario
     * @return bool True si existe, false si no
     */
    public function userExists(int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
