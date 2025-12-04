<?php
/**
 * TaskController.php
 * 
 * Controlador UNIFICADO de tareas con:
 * - Soporte para Admin y User
 * - Encriptación AES-256 de datos en tránsito
 * - Validación de archivos para completar tareas
 * - Ordenamiento inteligente para usuarios
 * - Métodos legacy para compatibilidad con frontend admin
 * - Logging con Monolog
 */

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../../smart/validators/TaskValidator.php';

class TaskController
{
    private $app;
    private $repository;
    private $validator;
    private $encryptionKey;

    // Constantes movidas a config.php - Solo mantener las que son específicas del controller
    const DEFAULT_DEADLINE_DAYS = 2;

    public function __construct($app)
    {
        $this->app = $app;
        $this->repository = new TaskRepository();
        $this->validator = new TaskValidator();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
    }

    // ============================================================
    // SECCIÓN: HELPERS DE RESPUESTA Y UTILIDADES
    // ============================================================

    /**
     * Respuesta de éxito en formato legacy
     */
    private function success($data, string $mensaje = 'Operación exitosa', int $code = 200)
    {
        return $this->sendLegacyResponse([
            'tipo' => 1,
            'mensajes' => [$mensaje],
            'data' => $data
        ], $code);
    }

    /**
     * Respuesta de error de validación
     */
    private function validationError(string $mensaje, int $code = 400)
    {
        return $this->sendLegacyResponse([
            'tipo' => 2,
            'mensajes' => [$mensaje],
            'data' => null
        ], $code);
    }

    /**
     * Respuesta de error del servidor
     */
    private function serverError(string $mensaje = 'Error interno del servidor')
    {
        return $this->sendLegacyResponse([
            'tipo' => 3,
            'mensajes' => [$mensaje],
            'data' => null
        ], 500);
    }

    /**
     * Procesar y validar archivos subidos
     * @return array ['files' => [...], 'error' => null] o ['files' => [], 'error' => 'mensaje']
     */
    private function processUploadedFiles(): array
    {
        $filesToProcess = [];
        
        // Crear directorio si no existe
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        // Caso 1: Campo único 'evidence'
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            $filesToProcess[] = $_FILES['evidence'];
        }
        
        // Caso 2: Campo múltiple 'evidences[]'
        if (isset($_FILES['evidences']) && is_array($_FILES['evidences']['name'])) {
            $count = count($_FILES['evidences']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['evidences']['error'][$i] === UPLOAD_ERR_OK) {
                    $filesToProcess[] = [
                        'name' => $_FILES['evidences']['name'][$i],
                        'type' => $_FILES['evidences']['type'][$i],
                        'tmp_name' => $_FILES['evidences']['tmp_name'][$i],
                        'error' => $_FILES['evidences']['error'][$i],
                        'size' => $_FILES['evidences']['size'][$i]
                    ];
                }
            }
        }
        
        $savedFiles = [];
        
        foreach ($filesToProcess as $file) {
            // Validar tamaño
            if ($file['size'] > MAX_FILE_SIZE_BYTES) {
                return ['files' => [], 'error' => "El archivo '{$file['name']}' excede el tamaño máximo de 1.5MB"];
            }
            
            // Validar tipo
            if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
                return ['files' => [], 'error' => 'Solo se permiten imágenes (JPEG, PNG, WebP)'];
            }
            
            $savedFiles[] = $file;
        }
        
        return ['files' => $savedFiles, 'error' => null];
    }

    /**
     * Guardar archivo en disco y retornar información
     */
    private function saveFileToDisk(array $file, int $taskId, int $userId): ?array
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueId = uniqid();
        $fileName = "tarea_{$taskId}_u{$userId}_{$uniqueId}.{$extension}";
        $filePath = UPLOAD_PATH_RELATIVE . $fileName;
        $fullPath = UPLOAD_DIR . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return null;
        }
        
        return [
            'path' => $filePath,
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }

    // ============================================================
    // SECCIÓN: MÉTODOS ADMIN (Formato Legacy)
    // ============================================================

    /**
     * GET /admin/tareas
     * Obtener todas las tareas admin con filtros opcionales
     * Query params: fecha, status, sucursal_id, categoria_id, assigned_user_id, sin_asignar
     */
    public function getAllTareasAdmin()
    {
        try {
            $request = $this->app->request();
            
            // Construir filtros desde query params
            $filtros = array_filter([
                'fecha' => $request->get('fecha'),
                'status' => $request->get('status'),
                'sucursal_id' => $request->get('sucursal_id'),
                'categoria_id' => $request->get('categoria_id'),
                'assigned_user_id' => $request->get('assigned_user_id'),
                'sin_asignar' => $request->get('sin_asignar') === 'true' ? true : null
            ], function($v) { return $v !== null; });
            
            // Obtener tareas con filtros (auto-inactiva vencidas)
            $tareas = $this->repository->getTareasConFiltros($filtros);
            
            return $this->success([
                "tareas" => $tareas,
                "total" => count($tareas),
                "filtros" => $filtros
            ], 'Tareas obtenidas correctamente');
        } catch (Exception $e) {
            Logger::error('Error al obtener tareas admin', ['error' => $e->getMessage()]);
            return $this->serverError('Error al obtener tareas');
        }
    }

    /**
     * GET /admin/tareas/:id
     * Obtener tarea específica (formato legacy)
     */
    public function getTareaAdminById($taskId)
    {
        try {
            $tarea = $this->repository->getTareaById((int)$taskId);
            
            if (!$tarea) {
                return $this->validationError('Tarea no encontrada', 404);
            }
            
            return $this->success($tarea, 'Tarea obtenida correctamente');
        } catch (Exception $e) {
            Logger::error('Error al obtener tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al obtener tarea');
        }
    }

    /**
     * GET /admin/tareas/fecha/:fecha
     * Obtener tareas por fecha (formato legacy)
     */
    public function getTareasAdminPorFecha($fecha)
    {
        try {
            $tareas = $this->repository->getTareasConFiltros(['fecha' => $fecha]);
            
            return $this->success([
                "tareas" => $tareas,
                "fecha" => $fecha,
                "total" => count($tareas)
            ], 'Tareas obtenidas para la fecha especificada');
        } catch (Exception $e) {
            Logger::error('Error al obtener tareas por fecha', ['error' => $e->getMessage()]);
            return $this->serverError('Error al obtener tareas');
        }
    }

    /**
     * POST /admin/tareas
     * Crear nueva tarea (formato legacy sin encriptación)
     */
    public function createTareaAdmin()
    {
        try {
            $userData = $this->getAuthenticatedUser();
            $userId = $userData['id'] ?? null;
            
            $data = json_decode($this->app->request()->getBody(), true);
            
            if (empty($data['titulo'])) {
                return $this->validationError('El título es requerido');
            }
            
            $taskId = $this->repository->crearTareaAdmin($data, $userId);
            $tarea = $this->repository->getTareaById($taskId);
            
            Logger::info('Tarea creada', ['id' => $taskId, 'user_id' => $userId]);
            return $this->success($tarea, 'Tarea creada correctamente', 201);
        } catch (Exception $e) {
            Logger::error('Error al crear tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al crear tarea');
        }
    }

    /**
     * PUT /admin/tareas/:id
     * Actualizar tarea (formato legacy)
     */
    public function updateTareaAdmin($taskId)
    {
        try {
            $data = json_decode($this->app->request()->getBody(), true);
            
            $this->repository->actualizarTareaAdmin((int)$taskId, $data);
            $tarea = $this->repository->getTareaById((int)$taskId);
            
            Logger::info('Tarea actualizada', ['id' => $taskId]);
            return $this->success($tarea, 'Tarea actualizada correctamente');
        } catch (Exception $e) {
            Logger::error('Error al actualizar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al actualizar tarea');
        }
    }

    /**
     * DELETE /admin/tareas/:id
     * Eliminar tarea (formato legacy - hard delete)
     */
    public function deleteTareaAdmin($taskId)
    {
        try {
            $this->repository->eliminarTareaAdmin((int)$taskId);
            
            Logger::info('Tarea eliminada', ['id' => $taskId]);
            return $this->success(['id' => $taskId], 'Tarea eliminada correctamente');
        } catch (Exception $e) {
            Logger::error('Error al eliminar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al eliminar tarea');
        }
    }

    /**
     * POST /admin/tareas/:id/asignar
     * Auto-asignar tarea al usuario autenticado
     */
    public function asignarTareaAdmin($taskId)
    {
        try {
            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? null;
            
            if (!$userId) {
                return $this->validationError('Usuario no autenticado', 401);
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea puede ser asignada
            if (!$this->repository->canBeAssigned($taskId)) {
                return $this->validationError('Esta tarea no está disponible para asignación');
            }
            
            // Validar que solo se pueden asignar tareas del día actual
            $tarea = $this->repository->getTareaById($taskId);
            if ($tarea) {
                $fechaTarea = $tarea['fechaAsignacion'];
                $hoy = date('Y-m-d');
                if ($fechaTarea < $hoy) {
                    return $this->validationError('Solo puedes auto-asignarte tareas del día de hoy');
                }
            }

            $this->repository->asignarTarea($taskId, $userId);
            
            Logger::info('Tarea asignada', ['tareaId' => $taskId, 'userId' => $userId]);
            return $this->success(['tareaId' => $taskId, 'userId' => $userId], 'Tarea asignada correctamente');
        } catch (Exception $e) {
            Logger::error('Error al asignar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al asignar tarea');
        }
    }

    /**
     * PUT /admin/tareas/:id/iniciar
     * Iniciar tarea (cambiar a 'En progreso')
     */
    public function iniciarTareaAdmin($taskId)
    {
        try {
            $taskId = (int)$taskId;
            
            // Validar que no se puede iniciar tareas de días anteriores
            $tarea = $this->repository->getTareaById($taskId);
            if ($tarea) {
                $fechaTarea = $tarea['fechaAsignacion'];
                $hoy = date('Y-m-d');
                if ($fechaTarea < $hoy) {
                    return $this->validationError('No puedes iniciar tareas de días anteriores');
                }
            }
            
            $this->repository->iniciarTarea($taskId);
            $tarea = $this->repository->getTareaById($taskId);
            
            Logger::info('Tarea iniciada', ['id' => $taskId]);
            return $this->success($tarea, 'Tarea iniciada correctamente');
        } catch (Exception $e) {
            Logger::error('Error al iniciar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al iniciar tarea');
        }
    }

    /**
     * POST /admin/tareas/:id/completar
     * Completar tarea con observaciones y evidencias (soporta múltiples imágenes)
     */
    public function completarTareaAdmin($taskId)
    {
        try {
            $taskId = (int)$taskId;
            $observaciones = $this->app->request()->post('observaciones') ?? '';
            
            // Obtener usuario autenticado
            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? null;
            
            // Procesar archivos subidos
            $uploadResult = $this->processUploadedFiles();
            if ($uploadResult['error']) {
                return $this->validationError($uploadResult['error']);
            }
            
            // Guardar cada archivo en disco
            $imagenesGuardadas = [];
            foreach ($uploadResult['files'] as $file) {
                $savedFile = $this->saveFileToDisk($file, $taskId, $userId);
                if (!$savedFile) {
                    return $this->serverError("Error al guardar el archivo: {$file['name']}");
                }
                $imagenesGuardadas[] = $savedFile;
            }
            
            // Registrar evidencias en BD
            if ($userId && !empty($imagenesGuardadas)) {
                $this->repository->guardarEvidencias($taskId, $userId, $imagenesGuardadas, $observaciones);
            }
            
            // Completar la tarea
            $this->repository->completarTarea($taskId, $observaciones);
            $tarea = $this->repository->getTareaById($taskId);
            
            $tarea['imagenes_guardadas'] = count($imagenesGuardadas);
            
            Logger::info('Tarea completada', ['id' => $taskId, 'imagenes' => count($imagenesGuardadas)]);
            return $this->success($tarea, 'Tarea completada correctamente');
            
        } catch (Exception $e) {
            Logger::error('Error al completar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al completar tarea');
        }
    }

    /**
     * PUT /admin/tareas/:id/reabrir
     * Reabrir tarea completada (formato legacy)
     */
    public function reabrirTareaAdmin($taskId)
    {
        try {
            $taskId = (int)$taskId;
            $data = json_decode($this->app->request()->getBody(), true);
            
            $motivo = $data['motivo'] ?? '';
            $observaciones = $data['observaciones'] ?? null;
            
            if (empty($motivo)) {
                return $this->validationError('El motivo de reapertura es requerido');
            }
            
            // Obtener usuario autenticado
            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? 0;
            
            // Nuevos valores opcionales
            $newValues = [];
            if (isset($data['assigned_user_id'])) {
                $newValues['assigned_user_id'] = $data['assigned_user_id'];
            }
            if (isset($data['deadline']) || isset($data['fechaVencimiento'])) {
                $newValues['deadline'] = $data['deadline'] ?? $data['fechaVencimiento'];
            }
            if (isset($data['priority']) || isset($data['prioridad'])) {
                $newValues['priority'] = $data['priority'] ?? $this->priorityToInternal($data['prioridad'] ?? 'medium');
            }
            
            $result = $this->repository->reopen($taskId, $userId, $motivo, $observaciones, !empty($newValues) ? $newValues : null);
            
            if (!$result) {
                return $this->validationError('No se pudo reabrir la tarea. Verifica que esté completada o incompleta.');
            }
            
            $tarea = $this->repository->getTareaById($taskId);
            
            Logger::info('Tarea reabierta', ['id' => $taskId, 'motivo' => $motivo, 'por' => $userId]);
            return $this->success($tarea, 'Tarea reabierta correctamente');
        } catch (Exception $e) {
            Logger::error('Error al reabrir tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al reabrir tarea');
        }
    }
    
    /**
     * Convierte prioridad legacy a formato interno
     */
    private function priorityToInternal(string $priority): string
    {
        $map = ['Alta' => 'high', 'Media' => 'medium', 'Baja' => 'low'];
        return $map[$priority] ?? $priority;
    }

    // ============================================================
    // SECCIÓN: MÉTODOS PÚBLICOS (ENDPOINTS CON ENCRIPTACIÓN)
    // ============================================================

    /**
     * GET /
     * Listar tareas según rol del usuario
     * - Admin: Ve todas, puede filtrar por fecha
     * - User: Ve ordenadas por Prioridad > Estado > Deadline
     */
    public function getAll()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $isAdmin = $userData['role'] === 'admin';

            if ($isAdmin) {
                // Admin: obtener filtros de query params
                $filters = [
                    'fecha_inicio' => $this->app->request()->get('fecha_inicio'),
                    'fecha_fin' => $this->app->request()->get('fecha_fin'),
                    'status' => $this->app->request()->get('status'),
                    'priority' => $this->app->request()->get('priority')
                ];

                // Limpiar filtros vacíos
                $filters = array_filter($filters, function ($v) {
                    return $v !== null && $v !== '';
                });

                $tasks = $this->repository->getTareas(null, $filters);

                Logger::info('Admin consultó lista de tareas', [
                    'admin_id' => $userData['id'],
                    'filters' => $filters,
                    'count' => count($tasks),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } else {
                // Usuario: obtener sus tareas ordenadas + tareas disponibles
                $myTasks = $this->repository->getAllForUser($userData['id']);
                $availableTasks = $this->repository->getAvailableTasks();

                $tasks = [
                    'my_tasks' => $myTasks,
                    'available_tasks' => $availableTasks
                ];

                Logger::debug('Usuario consultó sus tareas', [
                    'user_id' => $userData['id'],
                    'my_tasks_count' => count($myTasks),
                    'available_count' => count($availableTasks)
                ]);
            }

            $count = $isAdmin ? count($tasks) : (count($tasks['my_tasks']) + count($tasks['available_tasks']));

            if ($count === 0) {
                $response = $this->validator->listEmpty();
            } else {
                $response = $this->validator->listSuccess($count);
            }

            $response['data'] = $tasks;
            return $this->sendResponse($response);

        } catch (Exception $e) {
            Logger::error('Error al listar tareas', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * POST /
     * Crear nueva tarea (solo Admin)
     */
    public function create()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            // Solo admin puede crear tareas
            if ($userData['role'] !== 'admin') {
                Logger::warning('Intento de crear tarea sin permisos', [
                    'user_id' => $userData['id'],
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            // Obtener y desencriptar datos
            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse($this->validator->invalidRequestFormat());
            }

            // Calcular deadline si viene vacío (+2 días) ANTES de validar
            if (empty($data['deadline'])) {
                $data['deadline'] = date('Y-m-d', strtotime('+' . self::DEFAULT_DEADLINE_DAYS . ' days'));
            }

            // Validar datos
            if (!$this->validator->validateCreate($data)) {
                return $this->sendResponse($this->validator->createValidationError());
            }

            // Establecer status por defecto usando constante global
            $data['status'] = STATUS_PENDING;

            // Sanitizar datos
            $data['title'] = trim($data['title']);
            $data['description'] = isset($data['description']) && !empty($data['description'])
                ? trim($data['description'])
                : null;
            $data['priority'] = trim($data['priority']);
            $data['deadline'] = trim($data['deadline']);

            // Crear tarea (pasamos el ID del usuario que crea)
            $taskId = $this->repository->create($data, $userData['id']);

            if ($taskId) {
                Logger::info('Tarea creada', [
                    'task_id' => $taskId,
                    'title' => $data['title'],
                    'priority' => $data['priority'],
                    'deadline' => $data['deadline'],
                    'created_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                $response = $this->validator->createSuccess($data['title']);
                $response['data'] = [
                    'task_id' => $taskId,
                    'title' => $data['title'],
                    'deadline' => $data['deadline'],
                    'priority' => $data['priority']
                ];
                return $this->sendResponse($response);
            }

            return $this->sendResponse($this->validator->createError());

        } catch (Exception $e) {
            Logger::error('Error al crear tarea', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * PUT /:id/assign
     * Asignar tarea
     * - User: Auto-asignación (si está disponible)
     * - Admin: Reasignar a cualquier usuario
     */
    public function assign($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea existe
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            $isAdmin = $userData['role'] === 'admin';

            if ($isAdmin) {
                // Admin: puede reasignar a cualquier usuario
                $data = $this->getDecryptedRequestData();
                
                if (!$data || empty($data['user_id'])) {
                    return $this->sendResponse($this->validator->incompleteData());
                }

                $targetUserId = (int)$data['user_id'];

                // Verificar que el usuario destino existe
                if (!$this->repository->userExists($targetUserId)) {
                    return $this->sendResponse($this->validator->userNotFound());
                }

                $result = $this->repository->assign($taskId, $targetUserId, $userData['id']);

                if ($result) {
                    $username = $this->repository->getUsernameById($targetUserId);

                    Logger::info('Tarea reasignada por admin', [
                        'task_id' => $taskId,
                        'assigned_to' => $targetUserId,
                        'assigned_by' => $userData['username'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    return $this->sendResponse($this->validator->assignSuccess($username ?? 'Usuario'));
                }
            } else {
                // Usuario: solo puede auto-asignarse si está disponible
                if (!$this->repository->isAvailable($taskId)) {
                    return $this->sendResponse($this->validator->alreadyAssigned());
                }

                $result = $this->repository->assign($taskId, $userData['id'], $userData['id']);

                if ($result) {
                    Logger::info('Usuario se auto-asignó tarea', [
                        'task_id' => $taskId,
                        'user_id' => $userData['id'],
                        'username' => $userData['username'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    return $this->sendResponse($this->validator->selfAssignSuccess());
                }
            }

            return $this->sendResponse($this->validator->assignError());

        } catch (Exception $e) {
            Logger::error('Error al asignar tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * POST /:id/complete
     * Completar tarea (solo usuario asignado)
     * Requiere imagen de evidencia
     * 
     * NOTA: Es POST porque Slim 2 tiene problemas leyendo $_FILES con PUT
     */
    public function complete($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea existe
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Verificar que la tarea está asignada al usuario
            if ((int)$task['assigned_user_id'] !== $userData['id']) {
                Logger::warning('Intento de completar tarea no asignada', [
                    'task_id' => $taskId,
                    'user_id' => $userData['id'],
                    'assigned_to' => $task['assigned_user_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->notAssignedToYou());
            }

            // Verificar que la tarea no está ya completada
            if ($task['status'] === 'completed') {
                return $this->sendResponse($this->validator->alreadyCompleted());
            }

            // Verificar que el estado permite completar
            $allowedStatuses = ['pending', 'in_process'];
            if (!in_array($task['status'], $allowedStatuses)) {
                return $this->sendResponse($this->validator->cannotComplete());
            }

            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
            
            // Usar helper para procesar archivos
            $uploadResult = $this->processUploadedFiles();
            $hasFiles = !empty($uploadResult['files']);

            // Requerimos al menos observaciones
            if (empty($observaciones) && !$hasFiles) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Debe proporcionar observaciones y/o imagen de evidencia.'],
                    'data' => null
                ]);
            }
            
            // Validar error de subida
            if ($uploadResult['error']) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => [$uploadResult['error']],
                    'data' => null
                ]);
            }

            $evidencePath = null;
            $imagenesGuardadas = [];

            foreach ($uploadResult['files'] as $file) {
                if (!$this->validator->validateCompletionImage($file)) {
                    return $this->sendResponse($this->validator->imageValidationError());
                }

                // Procesar y guardar imagen
                $savedFile = $this->saveFileToDisk($file, $taskId, $userData['id']);

                if (!$savedFile) {
                    return $this->sendResponse($this->validator->imageUploadError());
                }
                
                $imagenesGuardadas[] = $savedFile;
                if (!$evidencePath) {
                    $evidencePath = $savedFile['path'];
                }
            }
            
            // Guardar evidencias en BD
            if (!empty($imagenesGuardadas)) {
                $this->repository->guardarEvidencias($taskId, $userData['id'], $imagenesGuardadas, $observaciones);
            }

            // Completar tarea con observaciones
            $result = $this->repository->complete($taskId, $userData['id'], $observaciones, $evidencePath);

            if ($result) {
                Logger::info('Tarea completada', [
                    'task_id' => $taskId,
                    'user_id' => $userData['id'],
                    'username' => $userData['username'],
                    'imagenes' => count($imagenesGuardadas),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                $response = $this->validator->completeSuccess();
                $response['data'] = [
                    'completed_at' => date('Y-m-d H:i:s'),
                    'imagenes_guardadas' => count($imagenesGuardadas)
                ];
                return $this->sendResponse($response);
            }

            return $this->sendResponse($this->validator->completeError());

        } catch (Exception $e) {
            Logger::error('Error al completar tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * GET /:id
     * Obtener detalle de una tarea
     */
    public function getById($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $taskId = (int)$taskId;
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Usuario solo puede ver sus propias tareas o tareas disponibles
            if ($userData['role'] !== 'admin') {
                $isOwner = (int)$task['assigned_user_id'] === $userData['id'];
                $isAvailable = $task['assigned_user_id'] === null;

                if (!$isOwner && !$isAvailable) {
                    return $this->sendResponse($this->validator->permissionDenied());
                }
            }

            $response = [
                'tipo' => 1,
                'mensajes' => ["Tarea obtenida exitosamente."],
                'data' => $task
            ];

            return $this->sendResponse($response);

        } catch (Exception $e) {
            Logger::error('Error al obtener tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * PUT /:id/status
     * Actualiza el estado de una tarea (solo Admin)
     */
    public function updateStatus($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            // Solo admin puede cambiar estado manualmente
            if ($userData['role'] !== 'admin') {
                Logger::warning('Intento de cambiar estado sin permisos', [
                    'user_id' => $userData['id'],
                    'task_id' => $taskId,
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea existe
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Obtener y desencriptar datos
            $data = $this->getDecryptedRequestData();

            if (!$data || empty($data['status'])) {
                return $this->sendResponse($this->validator->incompleteData());
            }

            $newStatus = trim($data['status']);

            // Validar estado
            if (!$this->validator->validateStatus($newStatus)) {
                return $this->sendResponse($this->validator->invalidStatus());
            }

            // Actualizar estado
            $result = $this->repository->updateStatus($taskId, $newStatus);

            if ($result) {
                Logger::info('Estado de tarea actualizado', [
                    'task_id' => $taskId,
                    'old_status' => $task['status'],
                    'new_status' => $newStatus,
                    'updated_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                return $this->sendResponse($this->validator->statusUpdateSuccess($newStatus));
            }

            return $this->sendResponse($this->validator->statusUpdateError());

        } catch (Exception $e) {
            Logger::error('Error al actualizar estado de tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * DELETE /:id
     * Elimina una tarea (solo Admin)
     */
    public function delete($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            // Solo admin puede eliminar tareas
            if ($userData['role'] !== 'admin') {
                Logger::warning('Intento de eliminar tarea sin permisos', [
                    'user_id' => $userData['id'],
                    'task_id' => $taskId,
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea existe
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // No permitir eliminar tareas completadas (opcional)
            if ($task['status'] === 'completed') {
                return $this->sendResponse($this->validator->cannotDeleteCompleted());
            }

            // Eliminar tarea (soft delete - marca como inactive)
            $result = $this->repository->delete($taskId, $userData['id']);

            if ($result) {
                Logger::info('Tarea eliminada', [
                    'task_id' => $taskId,
                    'title' => $task['title'],
                    'deleted_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                return $this->sendResponse($this->validator->deleteSuccess());
            }

            return $this->sendResponse($this->validator->deleteError());

        } catch (Exception $e) {
            Logger::error('Error al eliminar tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * PUT /:id/reopen
     * Reabre una tarea completada o incompleta (solo Admin)
     */
    public function reopen($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            // Solo admin puede reabrir tareas
            if ($userData['role'] !== 'admin') {
                Logger::warning('Intento de reabrir tarea sin permisos', [
                    'user_id' => $userData['id'],
                    'task_id' => $taskId,
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            $taskId = (int)$taskId;

            // Verificar que la tarea existe
            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Solo se pueden reabrir tareas completadas o incompletas
            $allowedStatuses = ['completed', 'incomplete'];
            if (!in_array($task['status'], $allowedStatuses)) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Solo se pueden reabrir tareas completadas o incompletas.'],
                    'data' => null
                ]);
            }

            // Obtener datos de reapertura
            $data = $this->getDecryptedRequestData();

            if (!$data || empty($data['motivo'])) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['El motivo de reapertura es requerido.'],
                    'data' => null
                ]);
            }

            $motivo = trim($data['motivo']);
            $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : null;
            
            // Nuevos valores opcionales
            $newValues = [];
            if (isset($data['assigned_user_id'])) {
                $newValues['assigned_user_id'] = $data['assigned_user_id'];
            }
            if (isset($data['deadline']) || isset($data['fechaVencimiento'])) {
                $newValues['deadline'] = $data['deadline'] ?? $data['fechaVencimiento'];
            }
            if (isset($data['priority']) || isset($data['prioridad'])) {
                $newValues['priority'] = $data['priority'] ?? $this->priorityToInternal($data['prioridad'] ?? 'medium');
            }

            // Reabrir tarea
            $result = $this->repository->reopen($taskId, $userData['id'], $motivo, $observaciones, !empty($newValues) ? $newValues : null);

            if ($result) {
                Logger::info('Tarea reabierta', [
                    'task_id' => $taskId,
                    'previous_status' => $task['status'],
                    'motivo' => $motivo,
                    'reopened_by' => $userData['username'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                return $this->sendResponse([
                    'tipo' => 1,
                    'mensajes' => ['Tarea reabierta exitosamente.'],
                    'data' => [
                        'task_id' => $taskId,
                        'new_status' => 'pending',
                        'reopened_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            }

            return $this->sendResponse([
                'tipo' => 0,
                'mensajes' => ['Error al reabrir la tarea.'],
                'data' => null
            ]);

        } catch (Exception $e) {
            Logger::error('Error al reabrir tarea', [
                'task_id' => $taskId ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * GET /statistics
     * Obtiene estadísticas de tareas
     */
    public function getStatistics()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $isAdmin = $userData['role'] === 'admin';
            
            // Admin ve todas las estadísticas, usuario solo las suyas
            $userId = $isAdmin ? null : $userData['id'];
            $stats = $this->repository->getStatistics($userId);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Estadísticas obtenidas exitosamente.'],
                'data' => $stats
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener estadísticas', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * GET /available
     * Obtiene tareas disponibles para auto-asignación (solo del día actual)
     */
    public function getAvailable()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $tasks = $this->repository->getAvailableTasksForToday();

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => [count($tasks) . ' tareas disponibles para hoy.'],
                'data' => $tasks
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener tareas disponibles', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    /**
     * GET /users
     * Obtiene usuarios disponibles para asignación (solo Admin)
     */
    public function getAvailableUsers()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            if ($userData['role'] !== 'admin') {
                return $this->sendResponse($this->validator->adminRequired());
            }

            $users = $this->repository->getAvailableUsers();

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => [count($users) . ' usuarios disponibles.'],
                'data' => $users
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener usuarios', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return $this->sendResponse($this->validator->serverError());
        }
    }

    // ============================================================
    // SECCIÓN: MÉTODOS PRIVADOS DE APOYO
    // ============================================================

    /**
     * Obtiene el usuario autenticado desde el middleware JWT
     */
    private function getAuthenticatedUser(): ?array
    {
        return isset($this->app->user) ? $this->app->user : null;
    }

    /**
     * Obtiene y desencripta los datos del request
     */
    private function getDecryptedRequestData(): ?array
    {
        $requestBody = $this->app->request()->getBody();
        $encryptedData = json_decode($requestBody, true);

        if (!$encryptedData || !isset($encryptedData['payload']) || !isset($encryptedData['iv'])) {
            return null;
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
            return null;
        }
    }

    /**
     * Encripta datos usando AES-256-CBC
     */
    private function encryptData(string $data): array
    {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return [
            'payload' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Guarda la imagen de evidencia usando constantes globales
     */
    private function saveEvidenceImage(array $file, int $taskId, int $userId): ?string
    {
        try {
            // Crear directorio si no existe (usando constante global)
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }

            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = sprintf(
                'task_%d_user_%d_%s.%s',
                $taskId,
                $userId,
                date('YmdHis'),
                strtolower($extension)
            );

            $destination = UPLOAD_DIR . $filename;

            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return UPLOAD_PATH_RELATIVE . $filename;
            }

            return null;

        } catch (Exception $e) {
            Logger::error('Error al guardar imagen de evidencia', [
                'task_id' => $taskId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Envía la respuesta JSON
     */
    private function sendResponse(array $responseData): void
    {
        $this->app->contentType('application/json; charset=utf-8');
        echo json_encode([
            'tipo' => $responseData['tipo'],
            'mensajes' => $responseData['mensajes'],
            'data' => $responseData['data'] ?? null
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Envía respuesta en formato legacy (para endpoints admin)
     * Similar a sendResponse pero con manejo de status HTTP
     */
    private function sendLegacyResponse(array $data, int $statusCode = 200): void
    {
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

