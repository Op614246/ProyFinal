<?php

class SecurityMiddleware extends \Slim\Middleware
{
    private $validKey;

    public function __construct($key)
    {
        $this->validKey = $key;
    }

    public function call()
    {
        $app = $this->app;

        $request = $app->request();
        $response = $app->response();

        $reqKey = $request->headers('X-Api-Key');

        if (empty($reqKey)) {
            $reqKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;
        }

        if ($reqKey !== $this->validKey) {
            
            $response->header('Content-Type', 'application/json');
            $response->status(403);

            $response->body(json_encode([
                "tipo" => 3,
                "mensajes" => ["Acceso denegado: API Key inválida o ausente."],
                "data" => null
            ], JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->next->call();
    }
}
