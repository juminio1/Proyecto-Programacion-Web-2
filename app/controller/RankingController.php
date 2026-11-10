<?php

namespace app\controller;

use Redirect;

class RankingController
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

        $ranking = $this->model->obtenerRankingSemanal();

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