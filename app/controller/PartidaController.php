<?php

namespace app\controller;

use Log;
use PDOException;
use Redirect;
use Request;

class PartidaController
{
    private $partidaModel;
    private $preguntaModel;
    private $render;

    public function __construct($partidaModel, $render, $preguntaModel)
    {
        $this->partidaModel = $partidaModel;
        $this->render = $render;
        $this->preguntaModel = $preguntaModel;
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
            Log::error('Error al abandonar partida: ' . $error->getMessage());
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
            Log::error('Error al crear partida: ' . $error->getMessage());
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

            $resultado = $this->partidaModel->prepararPregunta((int) $partida['id'], $idUsuario);
            if (!empty($resultado['tiempoAgotado'])) {
                $_SESSION['errores_partida'] = [$resultado['mensaje']];
                Redirect::to('/inicio/show');
            }

            if (isset($resultado['error'])) {
                $_SESSION['errores_partida'] = [$resultado['error']];
                Redirect::to('/inicio/show');
            }

            $pregunta = $resultado['pregunta'];
            if (!$pregunta) {
                $_SESSION['errores_partida'] = ['No hay preguntas disponibles en este momento.'];
                Redirect::to('/inicio/show');
                return;
            }

            $datos = [
                'idPartida' => $partida['id'],
                'puntaje' => $resultado['puntaje'],
                'respuestasCorrectas' => $resultado['puntaje'],
                'idAsignacion' => $pregunta['idAsignacion'],
                'tiempoRestante' => $pregunta['tiempoRestante'],
                'tiempoRestanteMs' => $pregunta['tiempoRestanteMs'],
                'duracionPreguntaMs' => $pregunta['duracionPreguntaMs'],
                'categoria' => $pregunta['categoria'] ?? 'General',
                'colorCategoria' => $pregunta['color'] ?? '#777777',
                'numeroPregunta' => $pregunta['numeroPregunta'] ?? 1,
                'pregunta' => $pregunta['pregunta'] ?? 'Sin preguntas disponibles',
                'idPregunta' => $pregunta['id'] ?? 0,
                'opciones' => $pregunta['opciones'] ?? []
            ];

            $this->render->renderiza('partida', $datos);
        } catch (PDOException $error) {
            Log::error('Error al cargar la partida: ' . $error->getMessage());
            error_log('Error al cargar la partida: ' . $error->getMessage());
            $_SESSION['errores_partida'] = ['Hubo un problema al cargar la partida. Por favor, intentá nuevamente.'];
            Redirect::to('/inicio/show');
        }
    }

    public function vencer()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($idUsuario <= 0) {
            $this->responderJson(['error' => 'Sesión no iniciada.'], 401);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->responderJson(['error' => 'Método no permitido.'], 405);
        }

        $idAsignacion = filter_var(Request::post('id_asignacion'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($idAsignacion === false) {
            $this->responderJson(['error' => 'La asignación de pregunta no es válida.'], 400);
        }

        try {
            $resultado = $this->partidaModel->vencerPregunta($idUsuario, $idAsignacion);
            $this->responderJson($resultado, isset($resultado['error']) ? 409 : 200);
        } catch (PDOException $error) {
            Log::error('Error al verificar el vencimiento: ' . $error->getMessage());
            $this->responderJson(['error' => 'No pudimos verificar el tiempo restante.'], 500);
        }
    }

    public function reportar()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            $this->responderJson(['ok' => false, 'mensaje' => 'Tenés que iniciar sesión para reportar.'], 401);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->responderJson(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
        }

        try {
            $partida = $this->partidaModel->obtenerPartidaActiva($idUsuario);

            if (!$partida) {
                $this->responderJson(['ok' => false, 'mensaje' => 'No tenés una partida activa.'], 404);
            }

            $idPregunta = (int) Request::post('id_pregunta', 0);
            $motivo = (string) Request::post('motivo', '');

            $resultado = $this->preguntaModel->registrarReporte(
                $idPregunta,
                $idUsuario,
                (int) $partida['id'],
                $motivo
            );

            $this->responderJson(
                ['ok' => $resultado['ok'], 'mensaje' => $resultado['mensaje']],
                $resultado['codigo']
            );
        } catch (PDOException $error) {
            Log::error('Error al reportar pregunta: ' . $error->getMessage());
            error_log('Error al reportar pregunta: ' . $error->getMessage());

            $this->responderJson(
                ['ok' => false, 'mensaje' => 'No pudimos registrar el reporte. Intentá nuevamente.'],
                500
            );
        }
    }

    private function responderJson(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit();
    }

    public function responder()
    {
        $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($idUsuario <= 0) {
            if ($this->esPeticionAjax()) {
                $this->responderJson(['error' => 'Sesión no iniciada.']);
            }
            Redirect::to('/login/show');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Redirect::to('/partida/show');
            return;
        }

        try {
            $idAsignacion = filter_var(Request::post('id_asignacion'), FILTER_VALIDATE_INT);
            $respuesta = filter_var(Request::post('respuesta'), FILTER_VALIDATE_INT);

            if ($idAsignacion <= 0 || $respuesta <= 0) {
                $resultado = [
                    'error' => 'Debe seleccionar una respuesta para continuar.',
                    'correcta' => false
                ];
            } else {
                $resultado = $this->partidaModel->responderPregunta(
                    $idUsuario,
                    $idAsignacion,
                    $respuesta
                );
            }

            if ($this->esPeticionAjax()) {
                $this->responderJson($resultado);
            }

            if (!empty($resultado['tiempoAgotado'])) {
                $_SESSION['errores_partida'] = [$resultado['mensaje']];
                Redirect::to('/inicio/show');
            }

            if (!empty($resultado['error'])) {
                $_SESSION['errores_partida'] = [$resultado['error']];
            }

            Redirect::to('/partida/show');
        } catch (PDOException $error) {
            Log::error('Error al responder pregunta: ' . $error->getMessage());
            error_log('Error al responder pregunta: ' . $error->getMessage());

            if ($this->esPeticionAjax()) {
                $this->responderJson(['error' => 'Ocurrió un error al procesar tu respuesta en el servidor.']);
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
