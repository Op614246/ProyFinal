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
     * Obtener todas las tareas admin con filtros opcionales
     * Query params: fecha, status, sucursal_id, categoria_id, assigned_user_id, sin_asignar
     */
    public function getAllTareasAdmin() {
        try {
            // Obtener filtros de query params
            $request = $this->app->request();
            $fecha = $request->get('fecha');
            $status = $request->get('status');
            $sucursalId = $request->get('sucursal_id');
            $categoriaId = $request->get('categoria_id');
            $assignedUserId = $request->get('assigned_user_id');
            $sinAsignar = $request->get('sin_asignar');
            
            // Construir filtros
            $filtros = [];
            if ($fecha) $filtros['fecha'] = $fecha;
            if ($status) $filtros['status'] = $status;
            if ($sucursalId) $filtros['sucursal_id'] = $sucursalId;
            if ($categoriaId) $filtros['categoria_id'] = $categoriaId;
            if ($assignedUserId) $filtros['assigned_user_id'] = $assignedUserId;
            if ($sinAsignar === 'true') $filtros['sin_asignar'] = true;
            
            // Obtener tareas con filtros
            $tareasAdmin = $this->repository->getTareasAdminConFiltros($filtros);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tareas admin obtenidas correctamente"],
                "data" => [
                    "tareas" => $tareasAdmin,
                    "total" => count($tareasAdmin),
                    "filtros" => $filtros
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
     * POST /admin/tareas/:id/asignar
     * Auto-asignar tarea al usuario autenticado
     */
    public function asignarTarea($tareaId) {
        try {
            // Obtener usuario autenticado del JWT (guardado por JwtMiddleware)
            $user = $this->app->user ?? null;
            $userId = $user['id'] ?? $user['user_id'] ?? null;
            
            if (!$userId) {
                return $this->sendResponse(['tipo' => 2, 'mensajes' => ['Usuario no autenticado'], 'data' => null], 401);
            }

            // Verificar que la tarea puede ser asignada
            if (!$this->repository->puedeSerAsignada($tareaId)) {
                return $this->sendResponse(['tipo' => 2, 'mensajes' => ['Esta tarea no está disponible para asignación'], 'data' => null], 400);
            }

            $this->repository->asignarTarea($tareaId, $userId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea asignada correctamente"],
                "data" => ['tareaId' => $tareaId, 'userId' => $userId]
            ];
            
            Logger::info('Tarea asignada', ['tareaId' => $tareaId, 'userId' => $userId]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al asignar tarea', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al asignar tarea'], 'data' => null], 500);
        }
    }

    /**
     * PUT /admin/tareas/:id/iniciar
     * Iniciar tarea (cambiar a 'En progreso')
     */
    public function iniciarTarea($tareaId) {
        try {
            $this->repository->iniciarTarea($tareaId);
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea iniciada correctamente"],
                "data" => $tarea
            ];
            
            Logger::info('Tarea iniciada', ['id' => $tareaId]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al iniciar tarea', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al iniciar tarea'], 'data' => null], 500);
        }
    }

    /**
     * POST /admin/tareas/:id/completar
     * Completar tarea con observaciones y evidencia
     */
    public function completarTarea($tareaId) {
        try {
            $request = $this->app->request();
            $observaciones = $request->post('observaciones') ?? '';
            $imagePath = null;
            
            // Procesar imagen si se envió
            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['evidence'];
                
                // Validar tamaño (max 1.5MB)
                $maxSize = 1.5 * 1024 * 1024; // 1.5MB en bytes
                if ($file['size'] > $maxSize) {
                    return $this->sendResponse([
                        'tipo' => 2, 
                        'mensajes' => ['La imagen excede el tamaño máximo de 1.5MB'], 
                        'data' => null
                    ], 400);
                }
                
                // Validar tipo de archivo (solo imágenes)
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowedTypes)) {
                    return $this->sendResponse([
                        'tipo' => 2, 
                        'mensajes' => ['Solo se permiten imágenes (JPEG, PNG, GIF, WebP)'], 
                        'data' => null
                    ], 400);
                }
                
                // Crear directorio de uploads si no existe
                $uploadDir = __DIR__ . '/../../uploads/evidencias/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generar nombre único
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'tarea_' . $tareaId . '_' . time() . '.' . $extension;
                $imagePath = 'uploads/evidencias/' . $fileName;
                
                // Mover archivo
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    return $this->sendResponse([
                        'tipo' => 3, 
                        'mensajes' => ['Error al guardar la imagen'], 
                        'data' => null
                    ], 500);
                }
                
                // Registrar evidencia en BD
                $user = $this->app->user ?? null;
                $userId = $user['id'] ?? $user['user_id'] ?? null;
                $this->repository->agregarEvidencia(
                    $tareaId, $userId, $imagePath, $file['name'],
                    $file['size'], $file['type'], $observaciones
                );
            }
            
            $this->repository->completarTarea($tareaId, $observaciones, $imagePath);
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea completada correctamente"],
                "data" => $tarea
            ];
            
            Logger::info('Tarea completada', ['id' => $tareaId]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al completar tarea', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al completar tarea'], 'data' => null], 500);
        }
    }

    /**
     * PUT /admin/tareas/:id/reabrir
     * Reabrir tarea completada
     */
    public function reabrirTarea($tareaId) {
        try {
            $request = $this->app->request();
            $data = json_decode($request->getBody(), true);
            
            $motivo = $data['motivo'] ?? '';
            $observaciones = $data['observaciones'] ?? null;
            
            if (empty($motivo)) {
                return $this->sendResponse([
                    'tipo' => 2, 
                    'mensajes' => ['El motivo de reapertura es requerido'], 
                    'data' => null
                ], 400);
            }
            
            $this->repository->reabrirTarea($tareaId, $motivo, $observaciones);
            $tarea = $this->repository->getTareaAdminPorId($tareaId);
            
            $response = [
                "tipo" => 1,
                "mensajes" => ["Tarea reabierta correctamente"],
                "data" => $tarea
            ];
            
            Logger::info('Tarea reabierta', ['id' => $tareaId, 'motivo' => $motivo]);
            return $this->sendResponse($response, 200);
        } catch (Exception $e) {
            Logger::error('Error al reabrir tarea', ['error' => $e->getMessage()]);
            return $this->sendResponse(['tipo' => 3, 'mensajes' => ['Error al reabrir tarea'], 'data' => null], 500);
        }
    }
    
    /**
     * Enviar respuesta
     */
    private function sendResponse($data, $statusCode = 200) {
        $response = $this->app->response();
        $response->header('Content-Type', 'application/json; charset=utf-8');
        $response->status($statusCode);
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        
        // Si hay error de JSON, loguear y devolver error genérico
        if ($json === false) {
            Logger::error('Error al codificar JSON', ['error' => json_last_error_msg()]);
            $json = json_encode([
                'tipo' => 3,
                'mensajes' => ['Error interno al procesar respuesta'],
                'data' => null
            ]);
        }
        
        $response->body($json);
    }
}
?>
