<?php
/**
 * TaskController.php
 * 
 * Controlador unificado de tareas con RBAC.
 * - Usa TaskConfig para constantes
 * - Admin: crear, asignar, reasignar, reabrir, eliminar
 * - User: listar propias, auto-asignar, completar
 */

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/TaskConfig.php';
require_once __DIR__ . '/../../smart/validators/TaskValidator.php';

class TaskController
{
    private $app;
    private $repository;
    private $validator;
    private $encryptionKey;

    public function __construct($app)
    {
        $this->app = $app;
        $this->repository = new TaskRepository();
        $this->validator = new TaskValidator();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
    }

    // ============================================================
    // SECCIÓN: MÉTODOS PÚBLICOS (ENDPOINTS)
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

            $isAdmin = TaskConfig::isAdmin($userData['role']);

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

                $tasks = $this->repository->getAllForAdmin($filters);

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
            if (!TaskConfig::isAdmin($userData['role'])) {
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
                $data['deadline'] = TaskConfig::getDefaultDeadline();
            }

            // Validar datos
            if (!$this->validator->validateCreate($data)) {
                return $this->sendResponse($this->validator->createValidationError());
            }

            // Establecer status por defecto
            $data['status'] = TaskConfig::STATUS_PENDING;

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
            $task = $this->repository->getById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            $isAdmin = TaskConfig::isAdmin($userData['role']);

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
            $task = $this->repository->getById($taskId);

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
            if ($task['status'] === TaskConfig::STATUS_COMPLETED) {
                return $this->sendResponse($this->validator->alreadyCompleted());
            }

            // Verificar que el estado permite completar
            if (!TaskConfig::canComplete($task['status'])) {
                return $this->sendResponse($this->validator->cannotComplete());
            }

            // Validar imagen de evidencia (opcional ahora)
            $hasImage = isset($_FILES['evidence']) && !empty($_FILES['evidence']['tmp_name']);
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

            // Requerimos al menos observaciones
            if (empty($observaciones) && !$hasImage) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Debe proporcionar observaciones y/o imagen de evidencia.'],
                    'data' => null
                ]);
            }

            $evidencePath = null;

            if ($hasImage) {
                $file = $_FILES['evidence'];

                // Validar tamaño máximo
                $fileSizeKb = $file['size'] / 1024;

                if ($fileSizeKb > TaskConfig::MAX_FILE_SIZE_KB) {
                    return $this->sendResponse([
                        'tipo' => 0,
                        'mensajes' => ['La imagen no puede exceder ' . (TaskConfig::MAX_FILE_SIZE_KB / 1024) . ' MB.'],
                        'data' => null
                    ]);
                }

                if (!$this->validator->validateCompletionImage($file)) {
                    return $this->sendResponse($this->validator->imageValidationError());
                }

                // Procesar y guardar imagen
                $evidencePath = $this->saveEvidenceImage($file, $taskId, $userData['id']);

                if (!$evidencePath) {
                    return $this->sendResponse($this->validator->imageUploadError());
                }

                // Guardar en task_evidence también
                $this->repository->addEvidence(
                    $taskId,
                    $userData['id'],
                    $evidencePath,
                    $file['name'],
                    (int)$fileSizeKb,
                    $file['type'],
                    $observaciones
                );
            }

            // Completar tarea con observaciones
            $result = $this->repository->complete($taskId, $userData['id'], $observaciones, $evidencePath);

            if ($result) {
                Logger::info('Tarea completada', [
                    'task_id' => $taskId,
                    'user_id' => $userData['id'],
                    'username' => $userData['username'],
                    'evidence_path' => $evidencePath,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                $response = $this->validator->completeSuccess();
                $response['data'] = [
                    'completed_at' => date('Y-m-d H:i:s'),
                    'evidence_image' => $evidencePath
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
            $task = $this->repository->getById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Usuario solo puede ver sus propias tareas o tareas disponibles
            if (!TaskConfig::isAdmin($userData['role'])) {
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
            if (!TaskConfig::isAdmin($userData['role'])) {
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
            $task = $this->repository->getById($taskId);

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
            if (!TaskConfig::isAdmin($userData['role'])) {
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
            $task = $this->repository->getById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // No permitir eliminar tareas completadas
            if ($task['status'] === TaskConfig::STATUS_COMPLETED) {
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
            if (!TaskConfig::isAdmin($userData['role'])) {
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
            $task = $this->repository->getById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            // Solo se pueden reabrir tareas completadas o incompletas
            if (!TaskConfig::canReopen($task['status'])) {
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

            // Datos adicionales para reapertura
            $newData = [
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'priority' => $data['priority'] ?? null
            ];

            // Reabrir tarea
            $result = $this->repository->reopen($taskId, $userData['id'], $motivo, $observaciones, $newData);

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
                        'new_status' => TaskConfig::STATUS_PENDING,
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

            $isAdmin = TaskConfig::isAdmin($userData['role']);
            
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

            if (!TaskConfig::isAdmin($userData['role'])) {
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
    // MÉTODOS PRIVADOS
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
     * Guarda la imagen de evidencia.
     */
    private function saveEvidenceImage(array $file, int $taskId, int $userId): ?string
    {
        try {
            $uploadDir = __DIR__ . '/../' . TaskConfig::UPLOAD_DIR;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = TaskConfig::generateEvidenceFileName($taskId, $userId, $file['name']);
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return TaskConfig::UPLOAD_DIR . $filename;
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
    
    private function sendResponse(array $responseData): void
    {
        $this->app->contentType('application/json; charset=utf-8');
        echo json_encode([
            'tipo' => $responseData['tipo'],
            'mensajes' => $responseData['mensajes'],
            'data' => $responseData['data'] ?? null
        ], JSON_UNESCAPED_UNICODE);
    }
}
