<?php

namespace app\controller;

use Request;
use Redirect;
use PDOException;

class LoginController{

    private $render;
    private $loginModel;
    private $partidaModel;

    public function __construct($loginModel, $render, $partidaModel){
        $this->loginModel = $loginModel;
        $this->render = $render;
        $this->partidaModel = $partidaModel;
    }

    public function show()
    {
        $this->render->renderiza("login");
    }

    public function login(){

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/login/show');
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

        $usuario = $this->loginModel->buscarUsuario($identificador);

        if ($usuario === false) {
            Redirect::to('/login/show');
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['nombre_usuario'];

        Redirect::to('/inicio/show');
    }

    public function logout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/inicio/show');
        }

        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        try {
            if ($idUsuario > 0) {
                $this->partidaModel->abandonarPartida($idUsuario);
            }
        } catch (PDOException $error) {
            error_log('Error al cerrar sesión: ' . $error->getMessage());

            $_SESSION['errores_partida'] = [
                'No se pudo cerrar sesión. Intentá nuevamente.'
            ];

            Redirect::to('/inicio/show');
        }

        // La partida se actualiza y cierra session
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 3600,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }

        session_destroy();

        Redirect::to('/login/show');
    }

}
