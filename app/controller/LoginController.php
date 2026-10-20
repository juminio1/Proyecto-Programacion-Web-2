<?php

namespace app\controller;

use Request;
use Redirect;

class LoginController{

    private $model;
    private $render;
    private $loginModel;

    public function __construct($loginModel, $render){
        $this->loginModel = $loginModel;
        $this->render = $render;
    }

    public function show()
    {
        $this->render->renderiza("login");
    }

    public function login(){

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/index.php?controller=login&method=show');
        }

        $identificador  = trim(Request::post('usuario', ''));
        $password = Request::post('password', '');

        $errores = $this->loginModel->iniciarSesion($identificador, $password);

        if (!empty($errores)) {
            $this->render->renderiza("login", [
                'hay_errores' => true,
                'errores' => $errores,
                'usuario' => $identificador
            ]);
            return;
        }

        $usuario = $this->loginModel->iniciarSesion($identificador, $password);

        if ($usuario === false) {
            Redirect::to('/index.php?controller=login&method=show');
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['nombre_usuario'];

        Redirect::to('/index.php?controller=inicio&method=show');
    }


}
