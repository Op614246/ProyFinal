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
    private $encryptionKey;
    
    public function __construct($app) {
        $this->app = $app;
        $this->repository = new SubtareaRepository();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
    }
    
    /**
     * Obtiene y desencripta los datos del request
     */
    private function getDecryptedRequestData(): ?array
    {
        $requestBody = $this->app->request()->getBody();
        $encryptedData = json_decode($requestBody, true);

        // Si no tiene payload/iv, intentar usar como JSON directo (para compatibilidad)
        if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
            return $encryptedData;
        }

        $decrypted = $this->decryptData($encryptedData['payload'], $encryptedData['iv']);

        if (!$decrypted) {
            return null;
        }

        return json_decode($decrypted, true);
    }

    /**
     * Desencripta datos usando AES-256-CBC
     */
    private function decryptData(string $encryptedPayload, string $iv): ?string
    {
        try {
            $key = hash('sha256', $this->encryptionKey, true);
            $iv = base64_decode($iv);
            $encrypted = base64_decode($encryptedPayload);

            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            return $decrypted ?: null;
        } catch (Exception $e) {
            Logger::error('Error desencriptando datos', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Obtener subtareas de una tarea
     * GET /tasks/:taskId/subtareas
     */
    public function getSubtareasByTask($taskId) {


        try {
            $subtareas = $this->repository->getSubtareasByTaskId($taskId);
            $stats = $this->repository->getEstadisticasSubtareas($taskId);

            // Log para depuración: cuántas subtareas y muestra de contenido
            Logger::debug('getSubtareasByTask payload', [
                'task_id' => $taskId,
                'count' => count($subtareas),
                'sample' => array_slice($subtareas, 0, 5)
            ]);

            // Devolver `data` como array de subtareas (esperado por el frontend)
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtareas obtenidas correctamente'],
                'data' => $subtareas,
                // Compatibilidad: también exponer `subtareas` como clave directa
                'subtareas' => $subtareas,
                'meta' => [
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
            $body = $this->getDecryptedRequestData();
            
            if (!$body) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['Formato de datos inválido'],
                    'data' => null
                ], 400);
            }
            
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
            
            $body = $this->getDecryptedRequestData();
            
            if (!$body) {
                return $this->sendResponse([
                    'tipo' => 2,
                    'mensajes' => ['Formato de datos inválido'],
                    'data' => null
                ], 400);
            }
            
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

            // Devolver `data` como array para mantener consistencia con otros endpoints
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtareas obtenidas correctamente'],
                'data' => $subtareas,
                // Compatibilidad hacia atrás: exponer subtareas también como campo directo
                'subtareas' => $subtareas,
                'meta' => [
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
     * Completar subtarea con evidencia (multipart/form-data)
     * POST /subtareas/:id/completar-con-evidencia
     */
    public function completarSubtareaConEvidencia($subtareaId) {
        try {
            // Asegurar que subtareaId sea entero
            $subtareaId = (int)$subtareaId;
            
            Logger::debug('Iniciando completar subtarea con evidencia', [
                'subtarea_id' => $subtareaId,
                'POST' => $_POST,
                'FILES' => !empty($_FILES) ? array_keys($_FILES) : []
            ]);
            
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            // Verificar si puede ser completada
            $canComplete = $this->repository->canCompleteSubtarea($subtareaId);
            if (!$canComplete['can_complete']) {
                Logger::debug('Subtarea no puede ser completada', $canComplete);
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => [$canComplete['reason'] ?? 'Esta subtarea no puede ser completada'],
                    'data' => null
                ], 400);
            }
            
            $observaciones = $_POST['observaciones'] ?? null;
            $usuarioId = $this->app->user['data']['id'] ?? null;
            
            if (!$observaciones || trim($observaciones) === '') {
                Logger::debug('Observaciones vacías o no recibidas', ['post' => $_POST]);
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Las observaciones son requeridas'],
                    'data' => null
                ], 400);
            }
            
            // 1. Completar la subtarea (actualizar estado)
            $this->repository->completarSubtarea($subtareaId, $usuarioId, trim($observaciones));
            
            // 2. Procesar imágenes si existen
            $evidenciasSubidas = [];
            if (!empty($_FILES['imagenes'])) {
                $evidenciasSubidas = $this->procesarEvidencias($subtareaId, $usuarioId);
            } elseif (!empty($_FILES['imagen'])) {
                // Soporte para imagen única
                $evidenciasSubidas = $this->procesarEvidenciaUnica($subtareaId, $usuarioId);
            }
            
            $subtareaActualizada = $this->repository->getSubtareaById($subtareaId);
            $subtareaActualizada['evidencias'] = $this->repository->getEvidenciasBySubtarea($subtareaId);
            
            Logger::info('Subtarea completada con evidencia', [
                'subtarea_id' => $subtareaId,
                'task_id' => $subtarea['task_id'],
                'evidencias_subidas' => count($evidenciasSubidas)
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Subtarea completada correctamente'],
                'data' => $subtareaActualizada
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al completar subtarea con evidencia', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al completar subtarea: ' . $e->getMessage()],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Obtener evidencias de una subtarea
     * GET /subtareas/:id/evidencias
     */
    public function getEvidencias($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $evidencias = $this->repository->getEvidenciasBySubtarea($subtareaId);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Evidencias obtenidas correctamente'],
                'data' => $evidencias
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al obtener evidencias', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al obtener evidencias'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Agregar evidencia a una subtarea
     * POST /subtareas/:id/evidencias
     */
    public function agregarEvidencia($subtareaId) {
        try {
            $subtarea = $this->repository->getSubtareaById($subtareaId);
            
            if (!$subtarea) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Subtarea no encontrada'],
                    'data' => null
                ], 404);
            }
            
            $usuarioId = $this->app->user['data']['id'] ?? null;
            $observaciones = $_POST['observaciones'] ?? null;
            
            if (empty($_FILES['imagen']) && empty($_FILES['archivo'])) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['No se recibió ningún archivo'],
                    'data' => null
                ], 400);
            }
            
            $archivo = $_FILES['imagen'] ?? $_FILES['archivo'];
            $resultado = $this->subirArchivo($archivo, $subtareaId, $usuarioId);
            
            if (!$resultado['success']) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => [$resultado['error']],
                    'data' => null
                ], 400);
            }
            
            // Guardar en base de datos
            $evidenciaId = $this->repository->agregarEvidencia(
                $subtareaId,
                $resultado['filename'],
                $resultado['tipo'],
                $resultado['nombre_original'],
                $resultado['tamanio'],
                $usuarioId,
                $observaciones
            );
            
            $evidencia = $this->repository->getEvidenciaById($evidenciaId);
            
            Logger::info('Evidencia agregada', [
                'subtarea_id' => $subtareaId,
                'evidencia_id' => $evidenciaId,
                'archivo' => $resultado['filename']
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Evidencia agregada correctamente'],
                'data' => $evidencia
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al agregar evidencia', [
                'subtarea_id' => $subtareaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al agregar evidencia'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Eliminar evidencia
     * DELETE /subtareas/:id/evidencias/:evidenciaId
     */
    public function eliminarEvidencia($subtareaId, $evidenciaId) {
        try {
            $evidencia = $this->repository->getEvidenciaById($evidenciaId);
            
            if (!$evidencia || $evidencia['subtarea_id'] != $subtareaId) {
                return $this->sendResponse([
                    'tipo' => 3,
                    'mensajes' => ['Evidencia no encontrada'],
                    'data' => null
                ], 404);
            }
            
            // Eliminar archivo físico
            $rutaArchivo = __DIR__ . '/../../uploads/evidencias/' . $evidencia['archivo'];
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
            
            // Eliminar de base de datos
            $this->repository->eliminarEvidencia($evidenciaId);
            
            Logger::info('Evidencia eliminada', [
                'subtarea_id' => $subtareaId,
                'evidencia_id' => $evidenciaId
            ]);
            
            $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Evidencia eliminada correctamente'],
                'data' => null
            ]);
            
        } catch (Exception $e) {
            Logger::error('Error al eliminar evidencia', [
                'subtarea_id' => $subtareaId,
                'evidencia_id' => $evidenciaId,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse([
                'tipo' => 3,
                'mensajes' => ['Error al eliminar evidencia'],
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Procesar múltiples evidencias
     */
    private function procesarEvidencias($subtareaId, $usuarioId) {
        $evidencias = [];
        $archivos = $_FILES['imagenes'];
        
        // Normalizar estructura de FILES para múltiples archivos
        $fileCount = is_array($archivos['name']) ? count($archivos['name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            if (is_array($archivos['name'])) {
                $archivo = [
                    'name' => $archivos['name'][$i],
                    'type' => $archivos['type'][$i],
                    'tmp_name' => $archivos['tmp_name'][$i],
                    'error' => $archivos['error'][$i],
                    'size' => $archivos['size'][$i]
                ];
            } else {
                $archivo = $archivos;
            }
            
            if ($archivo['error'] === UPLOAD_ERR_OK) {
                $resultado = $this->subirArchivo($archivo, $subtareaId, $usuarioId);
                
                if ($resultado['success']) {
                    $fileInfo = [
                        'tipo' => $resultado['tipo'],
                        'nombre_original' => $resultado['nombre_original'],
                        'tamanio' => $resultado['tamanio']
                    ];
                    $evidenciaId = $this->repository->agregarEvidencia(
                        $subtareaId,
                        $usuarioId,
                        $resultado['filename'],
                        $fileInfo,
                        null
                    );
                    $evidencias[] = $evidenciaId;
                }
            }
        }
        
        return $evidencias;
    }
    
    /**
     * Procesar evidencia única
     */
    private function procesarEvidenciaUnica($subtareaId, $usuarioId) {
        $evidencias = [];
        $archivo = $_FILES['imagen'];
        
        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $resultado = $this->subirArchivo($archivo, $subtareaId, $usuarioId);
            
            if ($resultado['success']) {
                $fileInfo = [
                    'tipo' => $resultado['tipo'],
                    'nombre_original' => $resultado['nombre_original'],
                    'tamanio' => $resultado['tamanio']
                ];
                $evidenciaId = $this->repository->agregarEvidencia(
                    $subtareaId,
                    $usuarioId,
                    $resultado['filename'],
                    $fileInfo,
                    null
                );
                $evidencias[] = $evidenciaId;
            }
        }
        
        return $evidencias;
    }
    
    /**
     * Subir archivo
     */
    private function subirArchivo($archivo, $subtareaId, $usuarioId = 0) {
        require_once __DIR__ . '/../core/TaskConfig.php';
        
        // Validar extensión
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $tiposPermitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
        
        if (!in_array($extension, $tiposPermitidos)) {
            return [
                'success' => false,
                'error' => 'Tipo de archivo no permitido'
            ];
        }
        
        // Validar tamaño (1.5MB máximo para imágenes)
        $maxSize = 1.5 * 1024 * 1024;
        if ($archivo['size'] > $maxSize) {
            return [
                'success' => false,
                'error' => 'El archivo excede el tamaño máximo permitido (1.5MB)'
            ];
        }
        
        // Generar nombre único
        $nombreArchivo = TaskConfig::generateSubtareaEvidenceFileName($subtareaId, $usuarioId, $archivo['name']);
        
        // Directorio de destino
        $directorio = __DIR__ . '/../../uploads/evidencias/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        $rutaDestino = $directorio . $nombreArchivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            // Determinar tipo
            $tipo = 'otro';
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $tipo = 'imagen';
            } elseif (in_array($extension, ['pdf', 'doc', 'docx'])) {
                $tipo = 'documento';
            }
            
            return [
                'success' => true,
                'filename' => $nombreArchivo,
                'tipo' => $tipo,
                'nombre_original' => $archivo['name'],
                'tamanio' => $archivo['size']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error al guardar el archivo'
        ];
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
