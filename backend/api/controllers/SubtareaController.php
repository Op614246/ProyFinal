<?php
/**
 * SubtareaController.php
 * 
 * Controlador para gestión de subtareas
 */

require_once __DIR__ . '/../core/Logger.php';

class SubtareaController {
    
    private $app;
    private $repository;
    
    public function __construct($app) {
        $this->app = $app;
        $this->repository = new SubtareaRepository();
    }
    
    /**
     * Obtener subtareas de una tarea
     * GET /tasks/:taskId/subtareas
     */
    public function getSubtareasByTask($taskId) {
        try {
            $subtareas = $this->repository->getSubtareasByTaskId($taskId);
            $stats = $this->repository->getEstadisticasSubtareas($taskId);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtareas obtenidas correctamente'],
                'data' => [
                    'subtareas' => $subtareas,
                    'estadisticas' => $stats,
                    'total' => count($subtareas)
                ]
            ]);
        } catch (Exception $e) {
            Logger::error('Error al obtener subtareas', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al obtener subtareas'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Obtener una subtarea por ID
     * GET /subtareas/:id
     */
    public function getSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea obtenida correctamente'],
                'data' => $subtarea
            ]);
        } catch (Exception $e) {
            Logger::error('Error al obtener subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al obtener subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Crear nueva subtarea
     * POST /tasks/:taskId/subtareas
     */
    public function crearSubtarea($taskId) {
        try {
            $body = json_decode($this->app->request()->getBody(), true);
            
            if (empty($body['titulo'])) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['El título es requerido'],
                    'data' => null
                ], 400);
            }
            
            $data = [
                'task_id' => $taskId,
                'titulo' => trim($body['titulo']),
                'descripcion' => $body['descripcion'] ?? null,
                'estado' => $body['estado'] ?? 'Pendiente',
                'prioridad' => $body['prioridad'] ?? 'Media',
                'fechaAsignacion' => $body['fechaAsignacion'] ?? date('Y-m-d'),
                'fechaVencimiento' => $body['fechaVencimiento'] ?? null,
                'horainicio' => $body['horainicio'] ?? null,
                'horafin' => $body['horafin'] ?? null,
                'categoria_id' => $body['categoria_id'] ?? null,
                'usuarioasignado_id' => $body['usuarioasignado_id'] ?? null
            ];
            
            $subtareaId = $this->repository->crearSubtarea($data);
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            Logger::info('Subtarea creada', [
                'subtarea_id' => $subtareaId,
                'task_id' => $taskId,
                'usuario' => $this->app->user['data']['username'] ?? 'unknown'
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea creada correctamente'],
                'data' => $subtarea
            ], 201);
            
        } catch (InvalidArgumentException $e) {
            Logger::warning('Creación de subtarea falló por datos inválidos', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 2,
                'mensajes' => [$e->getMessage()],
                'data' => null
            ], 400);
        } catch (Exception $e) {
            Logger::error('Error al crear subtarea', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al crear subtarea: ' . $e->getMessage()],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Crear nueva subtarea (task_id viene en el body)
     * POST /subtareas
     */
    public function crearSubtareaGeneral() {
        try {
            $body = json_decode($this->app->request()->getBody(), true);
            
            if (empty($body['task_id'])) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['El task_id es requerido'],
                    'data' => null
                ], 400);
            }
            
            if (empty($body['titulo'])) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['El título es requerido'],
                    'data' => null
                ], 400);
            }
            
            $taskId = $body['task_id'];
            
            $data = [
                'task_id' => $taskId,
                'titulo' => trim($body['titulo']),
                'descripcion' => $body['descripcion'] ?? null,
                'estado' => $body['estado'] ?? 'Pendiente',
                'prioridad' => $body['prioridad'] ?? 'Media',
                'fechaAsignacion' => $body['fechaAsignacion'] ?? date('Y-m-d'),
                'fechaVencimiento' => $body['fechaVencimiento'] ?? null,
                'horainicio' => $body['horainicio'] ?? null,
                'horafin' => $body['horafin'] ?? null,
                'categoria_id' => $body['categoria_id'] ?? null,
                'usuarioasignado_id' => $body['usuarioasignado_id'] ?? $body['usuario_asignado_id'] ?? null
            ];
            
            $subtareaId = $this->repository->crearSubtarea($data);
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            Logger::info('Subtarea creada (general)', [
                'subtarea_id' => $subtareaId,
                'task_id' => $taskId,
                'usuario' => $this->app->user['data']['username'] ?? 'unknown'
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea creada correctamente'],
                'data' => $subtarea
            ], 201);
            
        } catch (InvalidArgumentException $e) {
            Logger::warning('Creación de subtarea general falló por datos inválidos', [
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 2,
                'mensajes' => [$e->getMessage()],
                'data' => null
            ], 400);
        } catch (Exception $e) {
            Logger::error('Error al crear subtarea general', [
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al crear subtarea: ' . $e->getMessage()],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Actualizar subtarea
     * PUT /subtareas/:id
     */
    public function actualizarSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $body = json_decode($this->app->request()->getBody(), true);
            
            $result = $this->repository->actualizarSubtarea($subtareaId, $body);
            $subtareaActualizada = $this->repository->getSubtareaById($subtareaId);
            
            Logger::info('Subtarea actualizada', [
                'subtarea_id' => $subtareaId,
                'cambios' => array_keys($body)
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea actualizada correctamente'],
                'data' => $subtareaActualizada
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al actualizar subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al actualizar subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Eliminar subtarea
     * DELETE /subtareas/:id
     */
    public function eliminarSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $this->repository->eliminarSubtarea($subtareaId);
            
            Logger::info('Subtarea eliminada', [
                'subtarea_id' => $subtareaId,
                'task_id' => $subtarea['task_id']
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea eliminada correctamente'],
                'data' => null
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al eliminar subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Completar subtarea
     * PUT /subtareas/:id/completar
     */
    public function completarSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $body = json_decode($this->app->request()->getBody(), true);
            $observaciones = $body['observaciones'] ?? null;
            
            $this->repository->completarSubtarea($subtareaId, $observaciones);
            $subtareaActualizada = $this->repository->getSubtareaById($subtareaId);
            
            Logger::info('Subtarea completada', [
                'subtarea_id' => $subtareaId,
                'task_id' => $subtarea['task_id']
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea completada correctamente'],
                'data' => $subtareaActualizada
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al completar subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al completar subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Iniciar subtarea
     * PUT /subtareas/:id/iniciar
     */
    public function iniciarSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $this->repository->iniciarSubtarea($subtareaId);
            $subtareaActualizada = $this->repository->getSubtareaById($subtareaId);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea iniciada correctamente'],
                'data' => $subtareaActualizada
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al iniciar subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al iniciar subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Asignar subtarea a usuario
     * PUT /subtareas/:id/asignar
     */
    public function asignarSubtarea($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $body = json_decode($this->app->request()->getBody(), true);
            $usuarioId = $body['usuarioasignado_id'] ?? null;
            
            if (!$usuarioId) {
                // Auto-asignar al usuario actual
                $usuarioId = $this->app->user['data']['id'] ?? null;
            }
            
            if (!$usuarioId) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['Usuario no especificado'],
                    'data' => null
                ], 400);
            }
            
            $this->repository->asignarSubtarea($subtareaId, $usuarioId);
            $subtareaActualizada = $this->repository->getSubtareaById($subtareaId);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea asignada correctamente'],
                'data' => $subtareaActualizada
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al asignar subtarea', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al asignar subtarea'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Obtener subtareas del usuario actual
     * GET /subtareas/mis-subtareas
     */
    public function getMisSubtareas() {
        try {
            $usuarioId = $this->app->user['data']['id'] ?? null;
            
            if (!$usuarioId) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Usuario no autenticado'],
                    'data' => null
                ], 401);
            }
            
            $fecha = $this->app->request()->get('fecha');
            $subtareas = $this->repository->getSubtareasByUsuario($usuarioId, $fecha);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtareas obtenidas correctamente'],
                'data' => [
                    'subtareas' => $subtareas,
                    'total' => count($subtareas)
                ]
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener mis subtareas', [
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al obtener subtareas'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Envía respuesta JSON
     */
    private function sendResponse($data, $status = 200) {
        $this->app->response()->status($status);
        $this->app->response()->header('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
