<?php

namespace app\controller;

use Redirect;

class InicioController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show()
    {
        $idUsuario = $_SESSION['usuario_id'] ?? null;

        $usuario = null;

        if ($idUsuario !== null) {
            $usuario = $this->model->buscarUsuarioPorId($idUsuario);
        }

        if (!$usuario) {
            \Redirect::to('/login/show');
            return;
        }

        $erroresPartida = $_SESSION['errores_partida'] ?? [];

        unset($_SESSION['errores_partida']);

        $datosHome = [
            'usuarioEncontrado' => true,
            'nombre' => $usuario['nombre_completo'],
            'nombreUsuario' => $usuario['nombre_usuario'],
            'hayErroresPartida' => !empty($erroresPartida),
            'erroresPartida' => $erroresPartida,
            // Hay que crear la tabla partidas para poder traer estos datos
            'puntaje' => 0,
            'posicion' => '-',
            'partidasJugadas' => 0,
            'ranking' => [],
            'ultimasPartidas' => [],
            'tienePartidas' => false
        ];

        $this->render->renderiza('inicio', $datosHome);
    }
}
