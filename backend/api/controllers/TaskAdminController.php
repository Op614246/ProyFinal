<?php
/**
 * TaskAdminController.php
 * Controlador para tareas admin
 */

require_once __DIR__ . '/../repository/TaskAdminRepository.php';
require_once __DIR__ . '/../core/Logger.php';

class TaskAdminController {
    private $app;
    private $repository;
    
    public function __construct($app) {
        $this->app = $app;
        $this->repository = new TaskAdminRepository();
    }
    
    /**
     * GET /admin/tareas
     * Obtener todas las tareas admin
     */
    public function getAllTareasAdmin() {
        try {
            $tareasAdmin = $this->repository->getAllTareasAdmin();
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tareas admin obtenidas correctamente"],
                "data" => [
                    "tareas" => $tareasAdmin,
                    "total" => count($tareasAdmin)
                ]
            ];
            
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al obtener tareas admin', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al obtener tareas admin'], 'data' => null], 500);
        }
    }
    
    /**
     * GET /admin/tareas/:id
     * Obtener tarea admin específica
     */
    public function getTareaAdminById($tareaId) {
        try {
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            if (!$tarea) {
                return $this->sendResponse(['tipo' => 2, 'mensajes' => ['Tarea admin no encontrada'], 'data' => null], 404);
            }
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea admin obtenida correctamente"],
                "data" => $tarea
            ];
            
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al obtener tarea admin', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al obtener tarea admin'], 'data' => null], 500);
        }
    }
    
    /**
     * GET /admin/tareas/fecha/:fecha
     * Obtener tareas admin por fecha
     */
    public function getTareasAdminPorFecha($fecha) {
        try {
            $tareasAdmin = $this->repository->getTareasAdminPorFecha($fecha);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tareas admin obtenidas para la fecha especificada"],
                "data" => [
                    "tareas" => $tareasAdmin,
                    "fecha" => $fecha,
                    "total" => count($tareasAdmin)
                ]
            ];
            
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al obtener tareas admin por fecha', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al obtener tareas admin'], 'data' => null], 500);
        }
    }
    
    /**
     * POST /admin/tareas
     * Crear nueva tarea admin
     */
    public function createTareaAdmin() {
        try {
            $request = $this->app->request();
            $data = json_decode($request->getBody(), true);
            
            if (!isset($data['titulo']) || empty($data['titulo'])) {
                return $this->sendResponse(['tipo' => 2, 'mensajes' => ['El título es requerido'], 'data' => null], 400);
            }
            
            $tareaId = $this->repository->crearTareaAdmin($data);
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea admin creada correctamente"],
                "data" => $tarea
            ];
            
            Logger::info('Tarea admin creada', ['id' => $tareaId]);
            return $this->sendResponse($response, 201);
        } catch (Exception $e) {
            Logger::error('Error al crear tarea admin', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al crear tarea admin'], 'data' => null], 500);
        }
    }
    
    /**
     * PUT /admin/tareas/:id
     * Actualizar tarea admin
     */
    public function updateTareaAdmin($tareaId) {
        try {
            $request = $this->app->request();
            $data = json_decode($request->getBody(), true);
            
            $this->repository->actualizarTareaAdmin($tareaId, $data);
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea admin actualizada correctamente"],
                "data" => $tarea
            ];
            
            Logger::info('Tarea admin actualizada', ['id' => $tareaId]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al actualizar tarea admin', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al actualizar tarea admin'], 'data' => null], 500);
        }
    }
    
    /**
     * DELETE /admin/tareas/:id
     * Eliminar tarea admin
     */
    public function deleteTareaAdmin($tareaId) {
        try {
            $this->repository->eliminarTareaAdmin($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea admin eliminada correctamente"],
                "data" => ['id' => $tareaId]
            ];
            
            Logger::info('Tarea admin eliminada', ['id' => $tareaId]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al eliminar tarea admin', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al eliminar tarea admin'], 'data' => null], 500);
        }
    }
    
    /**
     * Enviar respuesta
     */
    private function sendResponse($data, $statusCode = 200) {
        $response = $this->app->response();
        $response->header('Content-Type', 'application/json');
        $response->status($statusCode);
        $response->body(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
?>
