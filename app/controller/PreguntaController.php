<?php

namespace app\controller;

use app\model\PreguntaModel;

class PreguntaController
{
    private $preguntaModel;
    private $render;

    public function __construct($preguntaModel, $render)
    {
        $this->preguntaModel = $preguntaModel;
        $this->render = $render;
    }

    public function show()
    {
        $preguntas = $this->preguntaModel->obtenerPreguntas();

        $this->render->render("pregunta", [
            "preguntas" => $preguntas
        ]);
    }
}
