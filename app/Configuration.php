<?php

namespace app;

use app\model\EventoModel;
use app\controller\InicioController;
use app\controller\LoginController;
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

    public function getLoginController(){
        return new LoginController($this->getRender());
    }
    
    // Los privados

    private function getDatabase()
    {
        $config = parse_ini_file("config/config.ini");

        return new MyDatabase($config["db_host"],
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
