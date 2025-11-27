<?php
// api/config/DB.php

class DB
{
    public $dbh; 
    private static $instance;

    private function __construct()
    {
        try {
            // Leemos del .env usando getenv()
            $host = getenv('DB_HOST');
            $dbName = getenv('DB_NAME');
            $port = getenv('DB_PORT');
            $user = getenv('DB_USERNAME');
            $password = getenv('DB_PASSWORD');

            $dsn = 'mysql:host=' . $host .
                ';dbname='    . $dbName .
                ';port='      . $port .
                ';connect_timeout=15';

            $this->dbh = new PDO($dsn, $user, $password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (Exception $er) {
            // En producción nunca mostramos el error real de la BD
            exit("Error de conexión a la base de datos."); 
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