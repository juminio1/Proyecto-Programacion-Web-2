<?php

namespace app\controller;

use PDOException;
use Redirect;

class PartidaController
{
    private $partidaModel;
    private $render;

    public function __construct($partidaModel, $render)
    {
        $this->partidaModel = $partidaModel;
        $this->render = $render;
    }

    public function abandonar()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            Redirect::to('/login/show');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/inicio/show');
        }

        try {
            $this->partidaModel->abandonarPartida($idUsuario);
        } catch (PDOException $error) {
            error_log('Error al abandonar partida: ' . $error->getMessage());

            $_SESSION['errores_partida'] = [
                'No se pudo abandonar la partida. Intentá nuevamente.'
            ];
        }

        Redirect::to('/inicio/show');
    }

    public function crear()
    {
        $idUsuario = (Integer) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            Redirect::to('/login/show');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/inicio/show');
        }


        try {
            $errores = $this->partidaModel->crearPartida($idUsuario);
        } catch (PDOException $error) {
            error_log('Error al crear partida: ' . $error->getMessage());

            $errores = ['No se pudo guardar la partida. Intentá nuevamente.'];
        }

        if (!empty($errores)) {
            $_SESSION['errores_partida'] = $errores;
            Redirect::to('/inicio/show');
        }

        Redirect::to('/partida/show');
    }

    public function show()
    {
        $this->render->renderiza('partida');
    }
}