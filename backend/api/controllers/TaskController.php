<?php
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../../smart/validators/TaskValidator.php';
require_once __DIR__ . '/../core/TaskConfig.php';

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

    private function success($data, string $mensaje = 'Operación exitosa', int $code = 200)
    {
        return $this->sendLegacyResponse([
            'tipo' => 1,
            'mensajes' => [$mensaje],
            'data' => $data
        ], $code);
    }

    private function validationError(string $mensaje, int $code = 400)
    {
        return $this->sendLegacyResponse([
            'tipo' => 2,
            'mensajes' => [$mensaje],
            'data' => null
        ], $code);
    }

    private function serverError(string $mensaje = 'Error interno del servidor')
    {
        return $this->sendLegacyResponse([
            'tipo' => 3,
            'mensajes' => [$mensaje],
            'data' => null
        ], 500);
    }

    private function processUploadedFiles(): array
    {
        $filesToProcess = [];

        if (!is_dir(TaskConfig::UPLOAD_DIR)) {
            mkdir(TaskConfig::UPLOAD_DIR, 0755, true);
        }

        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
            $filesToProcess[] = $_FILES['evidence'];
        }

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
            if ($file['size'] > TaskConfig::MAX_FILE_SIZE_BYTES) {
                return ['files' => [], 'error' => "El archivo '{$file['name']}' excede el tamaño máximo de 1.5MB"];
            }

            if (!TaskConfig::isAllowedMimeType($file['type'])) {
                return ['files' => [], 'error' => 'Solo se permiten imágenes (JPEG, PNG, WebP)'];
            }

            $savedFiles[] = $file;
        }

        return ['files' => $savedFiles, 'error' => null];
    }

    private function saveFileToDisk(array $file, int $taskId, int $userId): ?array
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueId = uniqid();
        $fileName = "tarea_{$taskId}_u{$userId}_{$uniqueId}.{$extension}";
        $filePath = TaskConfig::UPLOAD_PATH_RELATIVE . $fileName;
        $fullPath = TaskConfig::UPLOAD_DIR . $fileName;

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

    public function getAllTareas()
    {
        try {
            $request = $this->app->request();

            // Construir filtros desde query params (GET parameters)
            $filtros = array_filter([
                'fecha' => $_GET['fecha'] ?? null,
                'fecha_inicio' => $_GET['fecha_inicio'] ?? null,
                'fecha_fin' => $_GET['fecha_fin'] ?? null,
                'status' => $_GET['status'] ?? null,
                'sucursal_id' => $_GET['sucursal_id'] ?? null,
                'categoria_id' => $_GET['categoria_id'] ?? null,
                'assigned_user_id' => $_GET['assigned_user_id'] ?? null,
                'sin_asignar' => ($_GET['sin_asignar'] ?? null) === 'true' ? true : null
            ], function ($v) {
                return $v !== null && $v !== '';
            });

            Logger::debug('getAllTareasAdmin filtros recibidos', $filtros);

            $tareas = $this->repository->getTareas(null, $filtros);

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

    public function getTareaById($taskId)
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

    public function getTareasPorFecha($fecha)
    {
        try {
            $tareas = $this->repository->getTareas(null, ['fecha' => $fecha]);

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

    public function asignarTarea($taskId)
    {
        try {
            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? null;

            if (!$userId) {
                return $this->validationError('Usuario no autenticado', 401);
            }

            $taskId = (int)$taskId;

            if (!$this->repository->canBeAssigned($taskId)) {
                return $this->validationError('Esta tarea no está disponible para asignación');
            }

            $tarea = $this->repository->getTareaById($taskId);
            if ($tarea) {
                $fechaTarea = $tarea['fechaAsignacion'];
                $hoy = date('Y-m-d');
                if ($fechaTarea < $hoy) {
                    return $this->validationError('Solo puedes auto-asignarte tareas del día de hoy');
                }
            }

            $this->repository->assign($taskId, $userId, $userId);

            Logger::info('Tarea asignada', ['tareaId' => $taskId, 'userId' => $userId]);
            return $this->success(['tareaId' => $taskId, 'userId' => $userId], 'Tarea asignada correctamente');
        } catch (Exception $e) {
            Logger::error('Error al asignar tarea', ['error' => $e->getMessage()]);
            return $this->serverError('Error al asignar tarea');
        }
    }
    public function iniciarTarea($taskId)
    {
        try {
            $taskId = (int)$taskId;

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

    public function completarTarea($taskId)
    {
        try {
            $taskId = (int)$taskId;
            $observaciones = $this->app->request()->post('observaciones') ?? '';

            // Validar que todas las subtareas estén completadas
            $subtareasIncompletas = $this->repository->getSubtareasIncompletas($taskId);
            if (!empty($subtareasIncompletas)) {
                return $this->validationError(
                    'No puedes completar la tarea. Aún hay ' . count($subtareasIncompletas) . ' subtarea(s) incompleta(s)'
                );
            }

            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? null;

            $uploadResult = $this->processUploadedFiles();
            if ($uploadResult['error']) {
                return $this->validationError($uploadResult['error']);
            }

            $imagenesGuardadas = [];
            foreach ($uploadResult['files'] as $file) {
                $savedFile = $this->saveFileToDisk($file, $taskId, $userId);
                if (!$savedFile) {
                    return $this->serverError("Error al guardar el archivo: {$file['name']}");
                }
                $imagenesGuardadas[] = $savedFile;
            }

            if ($userId && !empty($imagenesGuardadas)) {
                $this->repository->guardarEvidencias($taskId, $userId, $imagenesGuardadas, $observaciones);
            }

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

            $user = $this->getAuthenticatedUser();
            $userId = $user['id'] ?? $user['user_id'] ?? 0;

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

    private function priorityToInternal(string $priority): string
    {
        $map = ['Alta' => 'high', 'Media' => 'medium', 'Baja' => 'low'];
        return $map[$priority] ?? $priority;
    }

    public function create()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            if ($userData['role'] !== 'admin') {
                Logger::warning('Intento de crear tarea sin permisos', [
                    'user_id' => $userData['id'],
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse($this->validator->invalidRequestFormat());
            }

            if (empty($data['deadline'])) {
                $data['deadline'] = TaskConfig::getDefaultDeadline();
            }

            // Validar datos
            if (!$this->validator->validateCreate($data)) {
                return $this->sendResponse($this->validator->createValidationError());
            }
            $fechaAsignacion = $data['fecha_asignacion'] ?? date('Y-m-d H:i:s');
            $deadline = $data['deadline'] ?? TaskConfig::getDefaultDeadline($fechaAsignacion);

            $errorMsg = null;
            if (!TaskValidator::validateDeadlineAfterOrEqual($fechaAsignacion, $deadline, $errorMsg)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                return;
            }

            $data['status'] = TaskConfig::STATUS_PENDING;

            $data['title'] = trim($data['title']);
            $data['description'] = isset($data['description']) && !empty($data['description'])
                ? trim($data['description'])
                : null;
            $data['priority'] = trim($data['priority']);
            $data['deadline'] = trim($data['deadline']);

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

    public function updateStatus($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

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

            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            $data = $this->getDecryptedRequestData();

            if (!$data || empty($data['status'])) {
                return $this->sendResponse($this->validator->incompleteData());
            }

            $newStatus = trim($data['status']);

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

    public function delete($taskId)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            if ($userData['role'] !== TaskConfig::ROLE_ADMIN) {
                Logger::warning('Intento de eliminar tarea sin permisos', [
                    'user_id' => $userData['id'],
                    'task_id' => $taskId,
                    'role' => $userData['role'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return $this->sendResponse($this->validator->adminRequired());
            }

            $taskId = (int)$taskId;

            $task = $this->repository->getTareaById($taskId);

            if (!$task) {
                return $this->sendResponse($this->validator->taskNotFound());
            }

            if ($task['status_internal'] === 'completed') {
                return $this->sendResponse($this->validator->cannotDeleteCompleted());
            }

            $result = $this->repository->delete($taskId, $userData['id']);

            if ($result) {
                Logger::info('Tarea eliminada', [
                    'task_id' => $taskId,
                    'title' => $task['titulo'],
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

    public function getStatistics()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse($this->validator->invalidSession());
            }

            $isAdmin = $userData['role'] === TaskConfig::ROLE_ADMIN;

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

    private function saveEvidenceImage(array $file, int $taskId, int $userId): ?string
    {
        try {
            if (!is_dir(TaskConfig::UPLOAD_DIR)) {
                mkdir(TaskConfig::UPLOAD_DIR, 0755, true);
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

            $destination = TaskConfig::UPLOAD_DIR . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return TaskConfig::UPLOAD_PATH_RELATIVE . $filename;
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

    private function sendLegacyResponse(array $data, int $statusCode = 200): void
    {
        $response = $this->app->response();
        $response->header('Content-Type', 'application/json; charset=utf-8');
        $response->status($statusCode);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

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
