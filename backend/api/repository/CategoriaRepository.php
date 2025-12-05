<?php
class CategoriaRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

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

    public function delete(int $id): bool
    {
        $sql = "UPDATE categorias SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

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

    public function countTasks(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE categoria_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
