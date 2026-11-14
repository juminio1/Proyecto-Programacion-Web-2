<?php

namespace app\controller;

use app\model\enums\RolUsuario;
use Request;
use Redirect;
use RuntimeException;

class LoginController{

    private $render;
    private $crearLoginModel;
    private $abandonarPartida;

    public function __construct(callable $crearLoginModel, $render, callable $abandonarPartida){
        $this->crearLoginModel = $crearLoginModel;
        $this->render = $render;
        $this->abandonarPartida = $abandonarPartida;
    }

    public function show()
    {
        $aviso = $_SESSION['aviso_logout'] ?? null;
        unset($_SESSION['aviso_logout']);

        $this->render->renderiza("login", [
            'hay_errores' => $aviso !== null,
            'errores' => $aviso === null ? [] : [$aviso]
        ]);
    }

    public function login(){

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('/login/show');
        }

        $identificador  = trim(Request::post('usuario', ''));
        $password = Request::post('password', '');

        $loginModel = ($this->crearLoginModel)();
        $errores = $loginModel->iniciarSesion($identificador, $password);

        if (!empty($errores)) {
            $this->render->renderiza("login", [
                'hay_errores' => true,
                'errores' => $errores,
                'usuario' => $identificador
            ]);
            return;
        }

        $usuario = $loginModel->buscarUsuario($identificador);

        if ($usuario === false) {
            Redirect::to('/login/show');
        }

        session_regenerate_id(true);

        $_SESSION = [
            'usuario_id' => $usuario['id'],
            'usuario' => $usuario['nombre_usuario'],
            'rol' => $usuario['rol'],
        ];

        Redirect::to($usuario['rol'] === RolUsuario::EDITOR ? '/editor/show' : '/inicio/show');
    }

    public function logout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to(($_SESSION['rol'] ?? null) === RolUsuario::EDITOR ? '/editor/show' : '/inicio/show');
        }

        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
        $esJugador = ($_SESSION['rol'] ?? null) === RolUsuario::JUGADOR;

        try {
            if ($idUsuario > 0 && $esJugador) {
                ($this->abandonarPartida)($idUsuario);
            }
        } catch (RuntimeException $error) {
            error_log('Error al abandonar la partida al cerrar sesión: ' . $error->getMessage());

            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['aviso_logout'] = 'Cerraste sesión, pero no se pudo confirmar el abandono de la partida. Si sigue activa cuando vuelvas, entrá a la partida y abandonala.';
            Redirect::to('/login/show');
        }

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
