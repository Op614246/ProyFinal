<?php
/**
 * TaskAdminRepository.php
 * Gestiona datos de tareas admin y subtareas
 */

class TaskAdminRepository {
    private $db;
    
    public function __construct() {
        $this->db = DB::getInstance()->dbh;
    }
    
    /**
     * Obtener todas las tareas admin con sus subtareas
     */
    public function getAllTareasAdmin() {
        $sql = "
            SELECT 
                ta.id, ta.titulo, ta.estado, ta.fechaAsignacion, 
                ta.horaprogramada, ta.horainicio, ta.horafin, ta.sucursal,
                c.nombre as Categoria,
                e.nombre as created_by_nombre, e.apellido as created_by_apellido
            FROM tareas_admin ta
            LEFT JOIN categorias c ON ta.categoria_id = c.id
            LEFT JOIN empleados e ON ta.creada_por = e.id
            ORDER BY ta.fechaAsignacion DESC, ta.horaprogramada ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Para cada tarea admin, obtener sus subtareas
        foreach ($tareasAdmin as &$tarea) {
            $tarea['Tarea'] = $this->getSubtareasPorTareaAdmin($tarea['id']);
        }
        
        return $tareasAdmin;
    }
    
    /**
     * Obtener subtareas de una tarea admin específica
     */
    public function getSubtareasPorTareaAdmin($tareaAdminId) {
        $sql = "
            SELECT 
                s.id, s.titulo, s.descripcion, s.estado, s.prioridad,
                s.completada, s.progreso, s.fechaAsignacion, s.fechaVencimiento,
                c.nombre as Categoria,
                s.horainicio, s.horafin,
                e.id as usuarioasignado_id,
                CONCAT(e.nombre, ' ', e.apellido) as usuarioasignado,
                0 as totalSubtareas,
                0 as subtareasCompletadas
            FROM subtareas s
            LEFT JOIN categorias c ON s.categoria_id = c.id
            LEFT JOIN empleados e ON s.usuarioasignado_id = e.id
            WHERE s.tarea_admin_id = ?
            ORDER BY s.fechaAsignacion ASC, s.horainicio ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tareaAdminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener una tarea admin por ID con sus subtareas
     */
    public function getTareaAdminPorId($tareaAdminId) {
        $sql = "
            SELECT 
                ta.id, ta.titulo, ta.estado, ta.fechaAsignacion, 
                ta.horaprogramada, ta.horainicio, ta.horafin, ta.sucursal,
                c.nombre as Categoria,
                e.nombre as created_by_nombre, e.apellido as created_by_apellido
            FROM tareas_admin ta
            LEFT JOIN categorias c ON ta.categoria_id = c.id
            LEFT JOIN empleados e ON ta.creada_por = e.id
            WHERE ta.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tareaAdminId]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tarea) {
            $tarea['Tarea'] = $this->getSubtareasPorTareaAdmin($tareaAdminId);
        }
        
        return $tarea;
    }
    
    /**
     * Obtener tareas admin por fecha
     */
    public function getTareasAdminPorFecha($fecha) {
        $sql = "
            SELECT 
                ta.id, ta.titulo, ta.estado, ta.fechaAsignacion, 
                ta.horaprogramada, ta.horainicio, ta.horafin, ta.sucursal,
                c.nombre as Categoria
            FROM tareas_admin ta
            LEFT JOIN categorias c ON ta.categoria_id = c.id
            WHERE ta.fechaAsignacion = ?
            ORDER BY ta.horaprogramada ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fecha]);
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tareasAdmin as &$tarea) {
            $tarea['Tarea'] = $this->getSubtareasPorTareaAdmin($tarea['id']);
        }
        
        return $tareasAdmin;
    }
    
    /**
     * Obtener tareas admin por sucursal
     */
    public function getTareasAdminPorSucursal($sucursal) {
        $sql = "
            SELECT 
                ta.id, ta.titulo, ta.estado, ta.fechaAsignacion, 
                ta.horaprogramada, ta.horainicio, ta.horafin, ta.sucursal,
                c.nombre as Categoria
            FROM tareas_admin ta
            LEFT JOIN categorias c ON ta.categoria_id = c.id
            WHERE ta.sucursal = ?
            ORDER BY ta.fechaAsignacion DESC, ta.horaprogramada ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sucursal]);
        $tareasAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tareasAdmin as &$tarea) {
            $tarea['Tarea'] = $this->getSubtareasPorTareaAdmin($tarea['id']);
        }
        
        return $tareasAdmin;
    }
    
    /**
     * Crear nueva tarea admin
     */
    public function crearTareaAdmin($data) {
        $sql = "
            INSERT INTO tareas_admin (titulo, estado, fechaAsignacion, horaprogramada, categoria_id, horainicio, horafin, sucursal, creada_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        
        $catId = null;
        if (!empty($data['Categoria'])) {
            $cat_stmt = $this->db->prepare('SELECT id FROM categorias WHERE nombre = ?');
            $cat_stmt->execute([$data['Categoria']]);
            $catId = $cat_stmt->fetchColumn();
        }
        
        $stmt->execute([
            $data['titulo'] ?? '',
            $data['estado'] ?? 'Pendiente',
            $data['fechaAsignacion'] ?? date('Y-m-d'),
            $data['horaprogramada'] ?? null,
            $catId,
            $data['horainicio'] ?? null,
            $data['horafin'] ?? null,
            $data['sucursal'] ?? null,
            $data['creada_por'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualizar tarea admin
     */
    public function actualizarTareaAdmin($tareaAdminId, $data) {
        $updateFields = [];
        $params = [];
        
        $campos = ['titulo', 'estado', 'fechaAsignacion', 'horaprogramada', 'horainicio', 'horafin', 'sucursal'];
        foreach ($campos as $campo) {
            if (isset($data[$campo])) {
                $updateFields[] = "$campo = ?";
                $params[] = $data[$campo];
            }
        }
        
        if (isset($data['Categoria'])) {
            $cat_stmt = $this->db->prepare('SELECT id FROM categorias WHERE nombre = ?');
            $cat_stmt->execute([$data['Categoria']]);
            $catId = $cat_stmt->fetchColumn();
            $updateFields[] = "categoria_id = ?";
            $params[] = $catId;
        }
        
        if (empty($updateFields)) {
            return true;
        }
        
        $params[] = $tareaAdminId;
        $sql = "UPDATE tareas_admin SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Eliminar tarea admin (y sus subtareas automáticamente por foreign key cascade)
     */
    public function eliminarTareaAdmin($tareaAdminId) {
        $sql = "DELETE FROM tareas_admin WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$tareaAdminId]);
    }
}
?>
