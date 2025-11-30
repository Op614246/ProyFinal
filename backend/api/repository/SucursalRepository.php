<?php
/**
 * SucursalRepository.php
 * 
 * Repositorio para operaciones de sucursales
 */

class SucursalRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    /**
     * Obtiene todas las sucursales activas
     * 
     * @return array Lista de sucursales
     */
    public function getAll(): array
    {
        $sql = "SELECT id, nombre, direccion, created_at 
                FROM sucursales 
                WHERE activo = 1 
                ORDER BY nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una sucursal por ID
     * 
     * @param int $id ID de la sucursal
     * @return array|false Datos de la sucursal o false si no existe
     */
    public function getById(int $id)
    {
        $sql = "SELECT id, nombre, direccion, activo, created_at 
                FROM sucursales 
                WHERE id = :id 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una nueva sucursal
     * 
     * @param array $data Datos de la sucursal
     * @return int|false ID de la sucursal creada o false si falla
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO sucursales (nombre, direccion, activo, created_at) 
                VALUES (:nombre, :direccion, 1, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':nombre' => $data['nombre'],
            ':direccion' => $data['direccion'] ?? null
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Actualiza una sucursal existente
     * 
     * @param int $id ID de la sucursal
     * @param array $data Datos a actualizar
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['nombre', 'direccion', 'activo'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE sucursales SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Desactiva una sucursal (soft delete)
     * 
     * @param int $id ID de la sucursal
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "UPDATE sucursales SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Verifica si existe una sucursal con el nombre dado
     * 
     * @param string $nombre Nombre de la sucursal
     * @param int|null $excludeId ID a excluir de la búsqueda (para updates)
     * @return bool
     */
    public function existsByName(string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM sucursales WHERE nombre = :nombre AND activo = 1";
        $params = [':nombre' => $nombre];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Cuenta el número de tareas asociadas a una sucursal
     * 
     * @param int $id ID de la sucursal
     * @return int
     */
    public function countTasks(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE sucursal_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
