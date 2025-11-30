<?php
/**
 * CategoriaController.php
 * 
 * Controlador para gestión de categorías
 */

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/CryptoHelper.php';

class CategoriaController
{
    private $app;
    private $repository;
    private $encryptionKey;

    public function __construct($app)
    {
        $this->app = $app;
        $this->repository = new CategoriaRepository();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
    }

    /**
     * GET /
     * Listar todas las categorías activas
     */
    public function getAll()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['Sesión inválida o expirada.']
                ]);
            }

            $categorias = $this->repository->getAll();

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => [count($categorias) . ' categorías encontradas.'],
                'data' => $categorias
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener categorías', [
                'error' => $e->getMessage(),
                'user_id' => $userData['id'] ?? null
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

    /**
     * GET /:id
     * Obtener una categoría por ID
     */
    public function getById($id)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['Sesión inválida o expirada.']
                ]);
            }

            $categoria = $this->repository->getById((int)$id);

            if (!$categoria) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Categoría no encontrada.']
                ]);
            }

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Categoría encontrada.'],
                'data' => $categoria
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener categoría', [
                'error' => $e->getMessage(),
                'categoria_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

    /**
     * POST /
     * Crear nueva categoría (Solo Admin)
     */
    public function create()
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['Sesión inválida o expirada.']
                ]);
            }

            if ($userData['role'] !== 'admin') {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['No tienes permisos para crear categorías.']
                ]);
            }

            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Datos de solicitud inválidos.']
                ]);
            }

            // Validaciones
            if (empty($data['nombre'])) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['El nombre de la categoría es requerido.']
                ]);
            }

            if ($this->repository->existsByName($data['nombre'])) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Ya existe una categoría con ese nombre.']
                ]);
            }

            $categoriaId = $this->repository->create($data);

            if (!$categoriaId) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Error al crear la categoría.']
                ]);
            }

            Logger::info('Categoría creada', [
                'categoria_id' => $categoriaId,
                'nombre' => $data['nombre'],
                'created_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Categoría creada exitosamente.'],
                'data' => ['id' => $categoriaId]
            ]);

        } catch (Exception $e) {
            Logger::error('Error al crear categoría', [
                'error' => $e->getMessage()
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

    /**
     * PUT /:id
     * Actualizar categoría (Solo Admin)
     */
    public function update($id)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['Sesión inválida o expirada.']
                ]);
            }

            if ($userData['role'] !== 'admin') {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['No tienes permisos para actualizar categorías.']
                ]);
            }

            $categoria = $this->repository->getById((int)$id);

            if (!$categoria) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Categoría no encontrada.']
                ]);
            }

            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Datos de solicitud inválidos.']
                ]);
            }

            // Validar nombre único si se está cambiando
            if (!empty($data['nombre']) && $data['nombre'] !== $categoria['nombre']) {
                if ($this->repository->existsByName($data['nombre'], (int)$id)) {
                    return $this->sendResponse([
                        'tipo' => 0,
                        'mensajes' => ['Ya existe otra categoría con ese nombre.']
                    ]);
                }
            }

            $result = $this->repository->update((int)$id, $data);

            if (!$result) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Error al actualizar la categoría.']
                ]);
            }

            Logger::info('Categoría actualizada', [
                'categoria_id' => $id,
                'updated_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Categoría actualizada exitosamente.']
            ]);

        } catch (Exception $e) {
            Logger::error('Error al actualizar categoría', [
                'error' => $e->getMessage(),
                'categoria_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

    /**
     * DELETE /:id
     * Eliminar categoría (Solo Admin)
     */
    public function delete($id)
    {
        try {
            $userData = $this->getAuthenticatedUser();

            if (!$userData) {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['Sesión inválida o expirada.']
                ]);
            }

            if ($userData['role'] !== 'admin') {
                return $this->sendResponse([
                    'tipo' => -1,
                    'mensajes' => ['No tienes permisos para eliminar categorías.']
                ]);
            }

            $categoria = $this->repository->getById((int)$id);

            if (!$categoria) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Categoría no encontrada.']
                ]);
            }

            // Verificar si tiene tareas asociadas
            $taskCount = $this->repository->countTasks((int)$id);
            if ($taskCount > 0) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ["No se puede eliminar. Tiene $taskCount tareas asociadas."]
                ]);
            }

            $result = $this->repository->delete((int)$id);

            if (!$result) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Error al eliminar la categoría.']
                ]);
            }

            Logger::info('Categoría eliminada', [
                'categoria_id' => $id,
                'deleted_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Categoría eliminada exitosamente.']
            ]);

        } catch (Exception $e) {
            Logger::error('Error al eliminar categoría', [
                'error' => $e->getMessage(),
                'categoria_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

    // ============================================================
    // MÉTODOS PRIVADOS
    // ============================================================

    private function getAuthenticatedUser(): ?array
    {
        return isset($this->app->user) ? $this->app->user : null;
    }

    private function getDecryptedRequestData(): ?array
    {
        $requestBody = $this->app->request()->getBody();
        $requestData = json_decode($requestBody, true);

        if (!$requestData) {
            return null;
        }

        // Si viene encriptado
        if (isset($requestData['payload']) && $this->encryptionKey) {
            $decrypted = CryptoHelper::decrypt($requestData['payload'], $this->encryptionKey);
            return $decrypted ? json_decode($decrypted, true) : null;
        }

        return $requestData;
    }

    private function sendResponse(array $response): void
    {
        $this->app->contentType('application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
