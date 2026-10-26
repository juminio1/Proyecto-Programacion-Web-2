<?php

namespace app\controller;

use PDOException;
use Redirect;
use Request;

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
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            Redirect::to('/login/show');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/inicio/show');
            return;
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
            return;
        }

        Redirect::to('/partida/show');
    }

    public function show()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            Redirect::to('/login/show');
            return;
        }

        try {
            $partida = $this->partidaModel->obtenerPartidaActiva($idUsuario);

            if (!$partida) {
                Redirect::to('/inicio/show');
                return;
            }

            // Obtener la pregunta actual pendiente o seleccionar la siguiente
            $pregunta = $this->partidaModel->obtenerPreguntaActual($partida['id']);

            if (!$pregunta) {
                $pregunta = $this->partidaModel->obtenerSiguientePregunta($partida['id']);
            }

            $datos = [
                'idPartida' => $partida['id'],
                'puntaje' => (int) $partida['puntaje'],
                'respuestasCorrectas' => (int) $partida['puntaje'],
                'tiempoRestante' => 15,
                'categoria' => $pregunta['categoria'] ?? 'General',
                'numeroPregunta' => $pregunta['numeroPregunta'] ?? 1,
                'pregunta' => $pregunta['pregunta'] ?? 'Sin preguntas disponibles',
                'idPregunta' => $pregunta['id'] ?? 0,
                'opciones' => $pregunta['opciones'] ?? []
            ];

            $this->render->renderiza('partida', $datos);
        } catch (PDOException $error) {
            error_log('Error al cargar la partida: ' . $error->getMessage());
            $_SESSION['errores_partida'] = ['Hubo un problema al cargar la partida. Por favor, intentá nuevamente.'];
            Redirect::to('/inicio/show');
        }
    }

    public function responder()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            if ($this->esPeticionAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Sesión no iniciada.']);
                exit();
            }
            Redirect::to('/login/show');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/partida/show');
            return;
        }

        try {
            $partida = $this->partidaModel->obtenerPartidaActiva($idUsuario);

            if (!$partida) {
                if ($this->esPeticionAjax()) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'No tenés una partida activa en este momento.']);
                    exit();
                }
                Redirect::to('/inicio/show');
                return;
            }

            $idPregunta = (int) (Request::post('id_pregunta', 0));
            $respuesta = trim((string) Request::post('respuesta', ''));

            if ($idPregunta <= 0 || $respuesta === '') {
                $resultado = [
                    'error' => 'Debe seleccionar una respuesta para continuar.',
                    'correcta' => false
                ];
            } else {
                $resultado = $this->partidaModel->responderPregunta(
                    $partida['id'],
                    $idUsuario,
                    $idPregunta,
                    $respuesta
                );
            }

            if ($this->esPeticionAjax()) {
                header('Content-Type: application/json');
                echo json_encode($resultado);
                exit();
            }

            if (!empty($resultado['error'])) {
                $_SESSION['errores_partida'] = [$resultado['error']];
            }

            Redirect::to('/partida/show');
        } catch (PDOException $error) {
            error_log('Error al responder pregunta: ' . $error->getMessage());

            if ($this->esPeticionAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Ocurrió un error al procesar tu respuesta en el servidor.']);
                exit();
            }

            $_SESSION['errores_partida'] = ['Ocurrió un error al registrar tu respuesta.'];
            Redirect::to('/partida/show');
        }
    }

    private function esPeticionAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) &&
                strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || Request::post('ajax') !== null;
    }
}
