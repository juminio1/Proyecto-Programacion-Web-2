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

        $ultimasPartidas = [
            ['numero' => 12, 'fecha' => '10/10/2026', 'puntaje' => 80],
            ['numero' => 11, 'fecha' => '08/10/2026', 'puntaje' => 60],
            ['numero' => 10, 'fecha' => '05/10/2026', 'puntaje' => 100],
        ];

        $datosHome = [
            'nombre' => $_SESSION['usuario'] ?? 'Dorian',
            'puntaje' => 350,
            'posicion' => 12,
            'partidasJugadas' => 18,

            'ranking' => [
                ['nombre' => 'Mateo', 'puntaje' => 620],
                ['nombre' => 'Sofía', 'puntaje' => 580],
                ['nombre' => 'Lucas', 'puntaje' => 540]
            ],

            'ultimasPartidas' => $ultimasPartidas,
            'tienePartidas' => !empty($ultimasPartidas),
        ];

        $this->render->renderiza("inicio", $datosHome);
    }
}
