<?php

namespace app;

use app\model\InicioModel;
use app\model\RegistroModel;
use app\controller\InicioController;
use app\controller\RegistroController;
use app\controller\LoginController;
use app\model\LoginModel;
use MustacheRender;
use MyDatabase;
use Router;
use Mailer;

require_once("helper/Autoloader.php");

class Configuration
{
    public function __construct()
    {
    }

    public function getInicioController()
    {
        return new InicioController(
            new InicioModel($this->getDatabase()),
            $this->getRender()
        );
    }

    public function getRegistroController()
    {
        return new RegistroController(
            new RegistroModel($this->getDatabase()),
            $this->getRender(),
            new Mailer(),
            $this->getConfig()
        );
    }

    public function getLoginController()
    {
        return new LoginController(
            new LoginModel($this->getDatabase()),
            $this->getRender()
        );
    }


    private function getConfig()
    {
        $archivoConfig = dirname(__DIR__) . "/config/config.ini";
        $archivoConfigLocal = dirname(__DIR__) . "/config/config.local.ini";

        if (file_exists($archivoConfigLocal)) {
            $archivoConfig = $archivoConfigLocal;
        }

        if (!file_exists($archivoConfig)) {
            throw new \RuntimeException(
                "No se encontró el archivo de configuración: " . $archivoConfig
            );
        }

        $config = parse_ini_file($archivoConfig);

        if ($config === false) {
            throw new \RuntimeException(
                "No se pudo leer el archivo de configuración."
            );
        }

        return $config;
    }


    private function getDatabase()
    {
        $config = $this->getConfig();

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