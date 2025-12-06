<?php
class DB
{
    public $dbh; 
    private static $instance;

    private function __construct()
    {
        try {
            $host = getenv('DB_HOST');
            $dbName = getenv('DB_NAME');
            $port = getenv('DB_PORT');
            $user = getenv('DB_USERNAME');
            $password = getenv('DB_PASSWORD');

            $dsn = 'mysql:host=' . $host .
                ';dbname='    . $dbName .
                ';port='      . $port .
                ';charset=utf8mb4' .
                ';connect_timeout=15';

            $this->dbh = new PDO($dsn, $user, $password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ));

        } catch (Exception $er) {
            error_log("DB Connection Error");
            
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                "tipo" => 3,
                "mensajes" => ["Error de conexión a la base de datos."],
                "data" => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $object = __CLASS__;
            self::$instance = new $object;
        }
        return self::$instance;
    }
}