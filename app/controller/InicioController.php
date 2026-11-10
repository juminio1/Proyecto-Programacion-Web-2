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
            Redirect::to('/login/show');
            return;
        }

        $erroresPartida = $_SESSION['errores_partida'] ?? [];

        unset($_SESSION['errores_partida']);

        $puntajeAcumulado = $this->model->obtenerPuntajeAcumulado($idUsuario);
        $partidasJugadas = $this->model->obtenerPartidasJugadas($idUsuario);
        $ultimasPartidas = $this->model->obtenerHistorialPartidas($idUsuario, 3);
        $ranking = $this->model->obtenerRankingSemanal();

        $posicion = '-';

        foreach ($ranking as $indice => &$jugador) {

            $jugador['posicion'] = $indice + 1;

            if ((int) $jugador['id'] === (int) $idUsuario) {
                $posicion = $indice + 1;
            }
        }

        unset($jugador);

        $datosHome = [
            'usuarioEncontrado' => true,
            'nombre' => $usuario['nombre_completo'],
            'nombreUsuario' => $usuario['nombre_usuario'],
            'hayErroresPartida' => !empty($erroresPartida),
            'erroresPartida' => $erroresPartida,
            'puntaje' => $puntajeAcumulado,
            'posicion' => $posicion,
            'partidasJugadas' => $partidasJugadas,
            'ranking' => $ranking,
            'ultimasPartidas' => $ultimasPartidas,
            'tienePartidas' => !empty($ultimasPartidas)
        ];

        $this->render->renderiza('inicio', $datosHome);
    }

    public function ranking()
    {
        $idUsuario = $_SESSION['usuario_id'] ?? null;

        if ($idUsuario === null) {
            Redirect::to('/login/show');
            return;
        }

        $ranking = $this->model->obtenerRankingSemanal(null);

        foreach ($ranking as $indice => &$jugador) {
            $jugador['posicion'] = $indice + 1;
            $jugador['esYo'] = ((int) $jugador['id'] === (int) $idUsuario);
        }
        unset($jugador);

        $this->render->renderiza('ranking', [
            'ranking' => $ranking,
            'hayRanking' => !empty($ranking),
            'totalJugadores' => count($ranking)
        ]);
    }
}
