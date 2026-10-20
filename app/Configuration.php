<?php

namespace app;

use app\model\EventoModel;
use app\model\LoginModel;
use app\model\RegistroModel;
use app\controller\InicioController;
use app\controller\LoginController;
use app\controller\RegistroController;
use app\controller\LoginController;
use app\model\LoginModel;
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
        //$config = parse_ini_file("config/config.ini");
        $archivoConfig = "config/config.ini";

        if (file_exists("config/config.local.ini")) {
            $archivoConfig = "config/config.local.ini";
        }

        $config = parse_ini_file($archivoConfig);

        return new MyDatabase(
            $config["db_host"],
            $config["db_user"],
            $config["db_pass"],
            $config["db_name"]
        );
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
