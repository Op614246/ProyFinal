<?php
/**
 * CategoriaRepository.php
 * 
 * Repositorio para operaciones de categorías
 */

class CategoriaRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    /**
     * Obtiene todas las categorías activas
     * 
     * @return array Lista de categorías
     */
    public function getAll(): array
    {
        $sql = "SELECT id, nombre, descripcion, color, created_at 
                FROM categorias 
                WHERE activo = 1 
                ORDER BY nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una categoría por ID
     * 
     * @param int $id ID de la categoría
     * @return array|false Datos de la categoría o false si no existe
     */
    public function getById(int $id)
    {
        $sql = "SELECT id, nombre, descripcion, color, activo, created_at 
                FROM categorias 
                WHERE id = :id 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una nueva categoría
     * 
     * @param array $data Datos de la categoría
     * @return int|false ID de la categoría creada o false si falla
     */
    public function create(array $data)
    {
        $sql = "INSERT INTO categorias (nombre, descripcion, color, activo, created_at) 
                VALUES (:nombre, :descripcion, :color, 1, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':color' => $data['color'] ?? '#6366f1'
        ]);

        return $result ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Actualiza una categoría existente
     * 
     * @param int $id ID de la categoría
     * @param array $data Datos a actualizar
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['nombre', 'descripcion', 'color', 'activo'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE categorias SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Desactiva una categoría (soft delete)
     * 
     * @param int $id ID de la categoría
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "UPDATE categorias SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Verifica si existe una categoría con el nombre dado
     * 
     * @param string $nombre Nombre de la categoría
     * @param int|null $excludeId ID a excluir de la búsqueda (para updates)
     * @return bool
     */
    public function existsByName(string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM categorias WHERE nombre = :nombre AND activo = 1";
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
     * Cuenta el número de tareas asociadas a una categoría
     * 
     * @param int $id ID de la categoría
     * @return int
     */
    public function countTasks(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE categoria_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
