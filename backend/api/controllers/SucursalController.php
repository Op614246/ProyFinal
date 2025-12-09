<?php

class SucursalController
{
    private $app;
    private $repository;
    private $encryptionKey;

    public function __construct($app)
    {
        $this->app = $app;
        $this->repository = new SucursalRepository();
        $this->encryptionKey = getenv('ENCRYPTION_KEY');
    }

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

            $sucursales = $this->repository->getAll();

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => [count($sucursales) . ' sucursales encontradas.'],
                'data' => $sucursales
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener sucursales', [
                'error' => $e->getMessage(),
                'user_id' => $userData['id'] ?? null
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

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

            $sucursal = $this->repository->getById((int)$id);

            if (!$sucursal) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Sucursal no encontrada.']
                ]);
            }

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Sucursal encontrada.'],
                'data' => $sucursal
            ]);

        } catch (Exception $e) {
            Logger::error('Error al obtener sucursal', [
                'error' => $e->getMessage(),
                'sucursal_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

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
                    'mensajes' => ['No tienes permisos para crear sucursales.']
                ]);
            }

            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Datos de solicitud inválidos.']
                ]);
            }

            if (empty($data['nombre'])) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['El nombre de la sucursal es requerido.']
                ]);
            }

            if ($this->repository->existsByName($data['nombre'])) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Ya existe una sucursal con ese nombre.']
                ]);
            }

            $sucursalId = $this->repository->create($data);

            if (!$sucursalId) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Error al crear la sucursal.']
                ]);
            }

            Logger::info('Sucursal creada', [
                'sucursal_id' => $sucursalId,
                'nombre' => $data['nombre'],
                'created_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Sucursal creada exitosamente.'],
                'data' => ['id' => $sucursalId]
            ]);

        } catch (Exception $e) {
            Logger::error('Error al crear sucursal', [
                'error' => $e->getMessage()
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

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
                    'mensajes' => ['No tienes permisos para actualizar sucursales.']
                ]);
            }

            $sucursal = $this->repository->getById((int)$id);

            if (!$sucursal) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Sucursal no encontrada.']
                ]);
            }

            $data = $this->getDecryptedRequestData();

            if (!$data) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Datos de solicitud inválidos.']
                ]);
            }

            if (!empty($data['nombre']) && $data['nombre'] !== $sucursal['nombre']) {
                if ($this->repository->existsByName($data['nombre'], (int)$id)) {
                    return $this->sendResponse([
                        'tipo' => 0,
                        'mensajes' => ['Ya existe otra sucursal con ese nombre.']
                    ]);
                }
            }

            $result = $this->repository->update((int)$id, $data);

            if (!$result) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Error al actualizar la sucursal.']
                ]);
            }

            Logger::info('Sucursal actualizada', [
                'sucursal_id' => $id,
                'updated_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Sucursal actualizada exitosamente.']
            ]);

        } catch (Exception $e) {
            Logger::error('Error al actualizar sucursal', [
                'error' => $e->getMessage(),
                'sucursal_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

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
                    'mensajes' => ['No tienes permisos para eliminar sucursales.']
                ]);
            }

            $sucursal = $this->repository->getById((int)$id);

            if (!$sucursal) {
                return $this->sendResponse([
                    'tipo' => 0,
                    'mensajes' => ['Sucursal no encontrada.']
                ]);
            }

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
                    'mensajes' => ['Error al eliminar la sucursal.']
                ]);
            }

            Logger::info('Sucursal eliminada', [
                'sucursal_id' => $id,
                'deleted_by' => $userData['id']
            ]);

            return $this->sendResponse([
                'tipo' => 1,
                'mensajes' => ['Sucursal eliminada exitosamente.']
            ]);

        } catch (Exception $e) {
            Logger::error('Error al eliminar sucursal', [
                'error' => $e->getMessage(),
                'sucursal_id' => $id
            ]);
            return $this->sendResponse([
                'tipo' => -1,
                'mensajes' => ['Error interno del servidor.']
            ]);
        }
    }

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
