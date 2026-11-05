<?php

namespace app\controller;

use Request;
use Redirect;

class PreguntaSugeridaController
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
        if (empty($_SESSION['usuario_id'])) {
            Redirect::to('/login/show');
        }

        $categorias = $this->model->obtenerCategorias();

        $this->render->renderiza('preguntaSugerida', [
            'categorias' => $categorias
        ]);
    }

    public function guardar()
    {
        if (empty($_SESSION['usuario_id'])) {
            Redirect::to('/login/show');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/preguntaSugerida/show');
        }

        $usuarioId = (int) $_SESSION['usuario_id'];

        $pregunta = trim((string) Request::post('pregunta', ''));
        $categoriaId = (int) Request::post('categoria_id', 0);

        $opcionA = trim((string) Request::post('opcion_a', ''));
        $opcionB = trim((string) Request::post('opcion_b', ''));
        $opcionC = trim((string) Request::post('opcion_c', ''));
        $opcionD = trim((string) Request::post('opcion_d', ''));

        $respuestaCorrecta = strtoupper(
            trim((string) Request::post('respuesta_correcta', ''))
        );

        $errores = [];

        if ($pregunta === '') {
            $errores[] = 'La pregunta es obligatoria.';
        }

        if ($categoriaId <= 0 || !$this->model->categoriaExiste($categoriaId)) {
            $errores[] = 'Debés seleccionar una categoría válida.';
        }

        if ($opcionA === '' ||
            $opcionB === '' ||
            $opcionC === '' ||
            $opcionD === '') {

            $errores[] = 'Debés completar las cuatro opciones.';
        }

        if (!in_array($respuestaCorrecta, ['A', 'B', 'C', 'D'], true)) {
            $errores[] = 'Debés indicar cuál es la respuesta correcta.';
        }

        if (!empty($errores)) {
            $categorias = $this->model->obtenerCategorias();

            foreach ($categorias as &$categoria) {
                $categoria['seleccionada'] =
                    ((int) $categoria['id'] === $categoriaId);
            }
            unset($categoria);

            $this->render->renderiza('preguntaSugerida', [
                'hay_errores' => true,
                'errores' => $errores,
                'categorias' => $categorias,

                'pregunta' => $pregunta,

                'opcion_a' => $opcionA,
                'opcion_b' => $opcionB,
                'opcion_c' => $opcionC,
                'opcion_d' => $opcionD,

                'correcta_a' => $respuestaCorrecta === 'A',
                'correcta_b' => $respuestaCorrecta === 'B',
                'correcta_c' => $respuestaCorrecta === 'C',
                'correcta_d' => $respuestaCorrecta === 'D'
            ]);

            return;
        }

        $guardada = $this->model->guardarSugerencia(
            $usuarioId,
            $categoriaId,
            $pregunta,
            $opcionA,
            $opcionB,
            $opcionC,
            $opcionD,
            $respuestaCorrecta
        );

        if (!$guardada) {
            $categorias = $this->model->obtenerCategorias();

            $this->render->renderiza('preguntaSugerida', [
                'hay_errores' => true,
                'errores' => [
                    'No se pudo guardar la pregunta. Intentá nuevamente.'
                ],
                'categorias' => $categorias
            ]);

            return;
        }

        $this->render->renderiza('preguntaSugerida', [
            'exito' => true,
            'mensaje_exito' =>
                'Tu pregunta fue enviada correctamente y está en proceso de aceptación.',
            'categorias' => $this->model->obtenerCategorias()
        ]);
    }


}