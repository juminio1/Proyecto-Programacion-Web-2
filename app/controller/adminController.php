<?php

namespace app\controller;

class AdminController
{
    private $render;

    public function __construct($render)
    {
        $this->render = $render;
    }

    public function show(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        $this->render->renderiza('admin', [
            'nombreAdmin' => (string) ($_SESSION['usuario'] ?? 'Administrador'),
            'secciones' => [
                ['titulo' => 'Usuarios', 'descripcion' => 'Cantidad de jugadores, altas y actividad.'],
                ['titulo' => 'Partidas', 'descripcion' => 'Partidas jugadas, abandonadas y puntajes.'],
                ['titulo' => 'Preguntas', 'descripcion' => 'Preguntas por categoría, dificultad y reportes.'],
                ['titulo' => 'Contenido sugerido', 'descripcion' => 'Preguntas y categorías sugeridas por los jugadores.'],
            ],
        ]);
    }
}
