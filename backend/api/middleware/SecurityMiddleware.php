<?php
// api/middleware/SecurityMiddleware.php

class SecurityMiddleware extends \Slim\Middleware
{
    private $validKey;

    public function __construct($key)
    {
        $this->validKey = $key;
    }

    public function call()
    {
        // Obtenemos la instancia de Slim
        $app = $this->app;

        // En Slim 2.0 usamos request() como método
        $request = $app->request();
        $response = $app->response();

        // Slim 2.0 normaliza los headers - intentamos varias variantes
        // El header HTTP_X_API_KEY se convierte a X-Api-Key por Slim
        $reqKey = $request->headers('X-Api-Key');

        // Si no lo encuentra, buscar directamente en $_SERVER
        if (empty($reqKey)) {
            $reqKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        }

        // Validamos
        if ($reqKey !== $this->validKey) {
            // Rechazo inmediato
            $response->header('Content-Type', 'application/json');
            $response->status(403);

            $response->body(json_encode([
                "tipo" => 3,
                "mensajes" => ["Acceso denegado: API Key inválida o ausente."],
                "data" => null
            ], JSON_UNESCAPED_UNICODE)); // Forbidden

            return; // Detenemos la ejecución
        }

        // Si pasa, continuamos al siguiente paso
        $this->next->call();
    }
}
