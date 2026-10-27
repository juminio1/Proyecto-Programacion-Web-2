<?php

namespace app\controller;

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

        $this->render->renderiza("Pregunta", [
            "preguntas" => $preguntas
        ]);
    }
}
