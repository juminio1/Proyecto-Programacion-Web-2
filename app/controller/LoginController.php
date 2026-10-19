<?php

namespace app\controller;

use Redirect;
use Request;

class LoginController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model  = $model;
        $this->render = $render;
    }

    public function show()
    {
        $this->render->renderiza("login");
    }

    public function login()
    {
        $nombreUsuario = Request::post('usuario', '');
        $password      = Request::post('password', '');

        $usuario = $this->model->autenticar($nombreUsuario, $password);

        if ($usuario === null) {
            $this->render->renderiza("login", [
                'error'   => 'Usuario o contraseña incorrectos.',
                'usuario' => $nombreUsuario
            ]);
            return;
        }

        $_SESSION['usuario_id']      = $usuario['id'];
        $_SESSION['usuario']         = $usuario['nombre_usuario'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'] ?? $usuario['nombre_usuario'];

        Redirect::toIndex();
    }

    public function logout()
    {
        session_destroy();
        Redirect::toIndex();
    }
}
