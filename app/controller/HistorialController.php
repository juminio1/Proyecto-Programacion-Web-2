<?php

namespace app\controller;

use Redirect;

class HistorialController
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

        if ($idUsuario === null) {
            Redirect::to('/login/show');
            return;
        }

        $partidas = $this->model->obtenerHistorialPartidas($idUsuario);

        $this->render->renderiza('historial', [
            'partidas' => $partidas,
            'tienePartidas' => !empty($partidas),
            'totalPartidas' => count($partidas)
        ]);
    }
}