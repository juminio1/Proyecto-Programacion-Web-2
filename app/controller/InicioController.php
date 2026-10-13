<?php

namespace app\controller;

class InicioController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show()
    {
        $data = [
            "titulo" => "Preguntados",
            "mensaje" => "Hola mundo!",
            "integrantes" => "Dorian Villalba - Ramiro Bruzón, Lucas García - Julieta Miño"
        ];

        $this->render->renderiza("inicio", $data);
    }
}