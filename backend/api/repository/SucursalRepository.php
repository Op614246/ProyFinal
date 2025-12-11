<?php
class SucursalRepository
{
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->dbh;
    }

    /**
     * Convierte un array de datos en una entity Sucursal
     */
    private function arrayToEntity(array $data): Sucursal
    {
        return new Sucursal($data);
    }

    /**
     * Convierte una entity Sucursal a array para respuestas API
     */
    private function entityToArray(Sucursal $sucursal): array
    {
        return [
            'id' => $sucursal->getId(),
            'nombre' => $sucursal->getNombre(),
            'direccion' => $sucursal->getDireccion(),
            'activo' => $sucursal->getActivo(),
            'created_at' => $sucursal->getCreatedAt()
        ];
    }

    public function getAll(): array
    {
        $sql = "SELECT id, nombre, direccion, created_at 
                FROM sucursales 
                WHERE activo = 1 
                ORDER BY nombre";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById(int $id)
    {
        $sql = "SELECT id, nombre, direccion, activo, created_at 
                FROM sucursales 
                WHERE id = :id 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

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

    public function delete(int $id): bool
    {
        $sql = "UPDATE sucursales SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Obtiene una sucursal como entity
     */
    public function getByIdAsEntity(int $id): ?Sucursal
    {
        $data = $this->getById($id);
        if (!$data) {
            return null;
        }
        return $this->arrayToEntity($data);
    }

    /**
     * Obtiene todas las sucursales como entities
     */
    public function getAllAsEntities(): array
    {
        $data = $this->getAll();
        $entities = [];
        foreach ($data as $item) {
            $entities[] = $this->arrayToEntity($item);
        }
        return $entities;
    }

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

    public function countTasks(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM tasks WHERE sucursal_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return (int)$stmt->fetchColumn();
    }
}
