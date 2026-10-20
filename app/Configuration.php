<?php

namespace app;

use app\model\EventoModel;
use app\model\LoginModel;
use app\model\RegistroModel;
use app\controller\InicioController;
use app\controller\LoginController;
use app\controller\RegistroController;
use MustacheRender;
use MyDatabase;
use Router;

require_once("helper/Autoloader.php");

class Configuration
{

    public function __construct()
    {
    }

    public function getInicioController()
    {
        return new InicioController(
            $this->getRender()
        );
    }

    public function getRegistroController()
    {
        return new RegistroController(
            new RegistroModel($this->getDatabase()),
            $this->getRender()
        );
    }

    public function getLoginController(){
        return new LoginController(
            new LoginModel($this->getDatabase()),
            $this->getRender()
        );
    }

    // Los privados

    private function getDatabase()
    {
        $config = parse_ini_file(
            dirname(__DIR__) . "/config/config.ini"
        );

        $database = new MyDatabase(
            $config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"]
        );

        // INICIO REPARACION TEMPORAL
        $pdo = $database->getConexion();

        $pdo->exec(
            "ALTER TABLE usuarios
         MODIFY COLUMN password VARCHAR(255) NOT NULL"
        );

        $consulta = $pdo->prepare(
            "UPDATE usuarios
         SET password = :hash, activo = 1
         WHERE nombre_usuario = :usuario
           AND CHAR_LENGTH(password) = 32"
        );

        $consulta->execute([
            'hash' => '$2y$12$wAN/3P/1HaBdqSuztVJJUeWOm7aJha7cMo1DyMpxGJQvSncIZWD0u',
            'usuario' => 'ricardito'
        ]);
        // FIN REPARACION TEMPORAL

        return $database;
    }

    private function getRender()
    {
        return new MustacheRender();
    }

    public function getRouter()
    {
        return new Router($this, "inicio", "show");
    }
}
