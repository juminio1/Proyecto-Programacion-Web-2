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

        $puntajeSemanal = $this->model->obtenerPuntajeSemanal($idUsuario);
        $partidasSemanales = $this->model->obtenerPartidasJugadasSemanales($idUsuario);
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

        $categorias = $this->model->obtenerCategoriasHabilitadas();
        $clases = [
            'Historia' => 'categoria-historia',
            'Deportes' => 'categoria-deportes',
            'Ciencia' => 'categoria-ciencia',
            'Entretenimiento' => 'categoria-entretenimiento'
        ];
        foreach ($categorias as &$categoria) {
            $nombre = $categoria['nombre'];
            $color = trim($categoria['color'] ?? '');
            $categoria['color'] = preg_match('/\A#[0-9A-Fa-f]{6}\z/', $color) === 1 ? $color : null;
            $categoria['clase'] = $clases[$nombre] ?? 'categoria-nueva';
            $categoria['esHistoria'] = $nombre === 'Historia';
            $categoria['esDeportes'] = $nombre === 'Deportes';
            $categoria['esCiencia'] = $nombre === 'Ciencia';
            $categoria['esEntretenimiento'] = $nombre === 'Entretenimiento';
            $categoria['esNueva'] = !isset($clases[$nombre]);
        }
        unset($categoria);

        $datosHome = [
            'usuarioEncontrado' => true,
            'nombre' => $usuario['nombre_completo'],
            'nombreUsuario' => $usuario['nombre_usuario'],
            'hayErroresPartida' => !empty($erroresPartida),
            'erroresPartida' => $erroresPartida,
            'puntaje' => $puntajeSemanal,
            'posicion' => $posicion,
            'partidasJugadas' => $partidasSemanales,
            'ranking' => $ranking,
            'ultimasPartidas' => $ultimasPartidas,
            'tienePartidas' => !empty($ultimasPartidas),
            'categorias' => $categorias
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
