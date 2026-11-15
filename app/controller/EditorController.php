<?php

namespace app\controller;

use app\model\PreguntaSugeridaModel;
use DomainException;
use PDOException;
use Request;

class EditorController
{
    private PreguntaSugeridaModel $sugerencias;
    private $render;

    public function __construct(PreguntaSugeridaModel $sugerencias, $render)
    {
        $this->sugerencias = $sugerencias;
        $this->render = $render;
    }

    public function show(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        $this->render->renderiza('editor');
    }

    public function sugerencias(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');
        $_SESSION['editor_sugerencias_csrf'] ??= bin2hex(random_bytes(32));
        $aviso = $_SESSION['editor_sugerencias_aviso'] ?? [];
        unset($_SESSION['editor_sugerencias_aviso']);

        try {
            $pendientes = $this->sugerencias->obtenerPendientes();
        } catch (PDOException $error) {
            error_log('Error al consultar sugerencias: ' . $error->getMessage());
            http_response_code(500);
            $this->render->renderiza('editorSugerencias', [
                'mensajeError' => 'No se pudieron cargar las sugerencias. Volvé a intentar.'
            ]);
            return;
        }

        foreach ($pendientes as &$sugerencia) {
            $sugerencia['opciones'] = [];
            $correcta = strtoupper(trim($sugerencia['respuesta_correcta']));
            foreach (['A', 'B', 'C', 'D'] as $letra) {
                $sugerencia['opciones'][] = [
                    'letra' => $letra,
                    'texto' => $sugerencia['opcion_' . strtolower($letra)],
                    'correcta' => $correcta === $letra
                ];
            }
        }
        unset($sugerencia);

        $this->render->renderiza('editorSugerencias', [
            'pendientes' => $pendientes,
            'sinPendientes' => $pendientes === [],
            'tokenAprobacion' => $_SESSION['editor_sugerencias_csrf'],
            'mensajeExito' => $aviso['exito'] ?? null,
            'mensajeError' => $aviso['error'] ?? null
        ]);
    }

    public function aprobar(): void
    {
        $this->resolverSugerencia(true);
    }

    public function rechazar(): void
    {
        $this->resolverSugerencia(false);
    }

    private function resolverSugerencia(bool $aprobar): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        $token = Request::post('csrf_token');
        $tokenGuardado = $_SESSION['editor_sugerencias_csrf'] ?? null;
        if (!is_string($token) || !is_string($tokenGuardado) || !hash_equals($tokenGuardado, $token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Volvé a abrir el listado de sugerencias para realizar esta acción.';
            return;
        }

        $id = filter_var(Request::post('id_sugerencia'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        try {
            if ($id === false) {
                throw new DomainException('La sugerencia indicada no es válida.');
            }
            if ($aprobar) {
                $this->sugerencias->aprobar($id);
                $mensaje = 'Sugerencia aprobada. La pregunta ya está disponible para jugar.';
            } else {
                $this->sugerencias->rechazar($id);
                $mensaje = 'Sugerencia rechazada. La pregunta no se incorporó al juego.';
            }
            $_SESSION['editor_sugerencias_aviso'] = ['exito' => $mensaje];
        } catch (DomainException $error) {
            $_SESSION['editor_sugerencias_aviso'] = ['error' => $error->getMessage()];
        } catch (PDOException $error) {
            $accion = $aprobar ? 'aprobar' : 'rechazar';
            error_log('Error al ' . $accion . ' sugerencia: ' . $error->getMessage());
            $_SESSION['editor_sugerencias_aviso'] = [
                'error' => 'No se pudo guardar la ' . ($aprobar ? 'aprobación' : 'decisión') . '. Volvé a intentar.'
            ];
        }

        header('Location: /editor/sugerencias', true, 303);
        exit;
    }
}
