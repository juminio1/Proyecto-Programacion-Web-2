<?php

namespace app\controller;

use app\model\PreguntaSugeridaModel;
use app\model\EditorModel;
use app\model\CategoriaSugeridaModel;
use app\model\CategoriaModel;
use DomainException;
use PDOException;
use Request;

class EditorController
{
    private PreguntaSugeridaModel $sugerencias;
    private EditorModel $editorModel;
    private CategoriaSugeridaModel $categorias;
    private CategoriaModel $categoriaModel;
    private $render;

    public function __construct(
        PreguntaSugeridaModel $sugerencias,
        EditorModel $editorModel,
        CategoriaSugeridaModel $categorias,
        CategoriaModel $categoriaModel,
        $render
    ) {
        $this->sugerencias = $sugerencias;
        $this->editorModel = $editorModel;
        $this->categorias = $categorias;
        $this->categoriaModel = $categoriaModel;
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
        $datos = [];

        try {
            $categorias = $this->categoriaModel->obtenerCategoriasHabilitadasConPreguntas();

            foreach ($categorias as &$categoria) {
                $categoria['cantidad_preguntas'] = (int) $categoria['cantidad_preguntas'];
                $categoria['tienePreguntas'] = $categoria['cantidad_preguntas'] > 0;
            }
            unset($categoria);

            $datos['categoriasHabilitadas'] = $categorias;
            $datos['cantidadCategoriasHabilitadas'] = count($categorias);
            $datos['sinCategoriasHabilitadas'] = $categorias === [];
            $datos['categoriasCargadas'] = true;
        } catch (PDOException $error) {
            error_log('Error al cargar categorías habilitadas: ' . $error->getMessage());
            $datos['errorCategorias'] = 'No se pudieron cargar las categorías habilitadas. Intentá nuevamente.';
        }

        $this->render->renderiza('editor', $datos);
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

    public function mostrarCategoriasSugeridasPendientes(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Cache-Control: no-store');
            $_SESSION['editor_categorias_csrf'] ??= bin2hex(random_bytes(32));
            $aviso = $_SESSION['editor_categorias_aviso'] ?? [];
            unset($_SESSION['editor_categorias_aviso']);
            $datos = [
                'tokenCSRF' => $_SESSION['editor_categorias_csrf'],
                'mensajeExito' => $aviso['exito'] ?? '',
                'mensajeError' => $aviso['error'] ?? ''
            ];

            try {
                $datos['pendientes'] = $this->categorias->obtenerCategoriasSugeridasPendientes();
                $datos['sinPendientes'] = count($datos['pendientes']) === 0;
            } catch (PDOException $error) {
                error_log('Error al consultar categorías sugeridas: ' . $error->getMessage());
                $datos['mensajeError'] = 'No se pudieron cargar las categorías sugeridas. Intentá nuevamente.';
            }

            $this->render->renderiza('editorCategorias', $datos);
        } else {
            http_response_code(405);
            header('Allow: GET');
            echo 'Abrí el listado de categorías para revisarlas.';
        }
    }

    public function aprobarCategoriaSugerida(): void
    {
        $this->procesarRevisionCategoriaSugerida(true);
    }

    public function rechazarCategoriaSugerida(): void
    {
        $this->procesarRevisionCategoriaSugerida(false);
    }

    private function procesarRevisionCategoriaSugerida(bool $aprobar): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = Request::post('csrf_token');
            $tokenGuardado = $_SESSION['editor_categorias_csrf'] ?? '';
            $esTokenValido = is_string($token) && strlen($tokenGuardado) > 0
                && hash_equals($tokenGuardado, $token);

            if ($esTokenValido) {
                $_SESSION['editor_categorias_aviso'] = $this->guardarDecisionSobreCategoriaSugerida($aprobar);
            } else {
                $_SESSION['editor_categorias_aviso'] = [
                    'error' => 'Volvé a abrir el listado para revisar la categoría.'
                ];
            }

            header('Location: /editor/mostrarCategoriasSugeridasPendientes', true, 303);
        } else {
            http_response_code(405);
            header('Allow: POST');
            echo 'Usá los botones del listado para aprobar o rechazar una categoría.';
        }
    }

    private function guardarDecisionSobreCategoriaSugerida(bool $aprobar): array
    {
        $id = filter_var(Request::post('id_sugerencia'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        $aviso = ['error' => 'La sugerencia indicada no es válida.'];

        if (is_int($id) && $id > 0) {
            try {
                $editorId = (int) $_SESSION['usuario_id'];

                if ($aprobar) {
                    $this->categorias->aprobarCategoriaSugerida($id, $editorId);
                    $aviso = ['exito' => 'Categoría aprobada y habilitada. Ya está disponible para agregar preguntas.'];
                } else {
                    $this->categorias->rechazarCategoriaSugerida($id, $editorId);
                    $aviso = ['exito' => 'Sugerencia rechazada. La categoría no se incorporó al juego.'];
                }
            } catch (DomainException $error) {
                $aviso = ['error' => $error->getMessage()];
            } catch (PDOException $error) {
                error_log('Error al revisar categoría sugerida: ' . $error->getMessage());
                $aviso = ['error' => 'No se pudo guardar la decisión. Intentá nuevamente.'];
            }
        }

        return $aviso;
    }

    public function reportes(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        $_SESSION['editor_reportes_csrf'] ??= bin2hex(random_bytes(32));

        $aviso = $_SESSION['editor_reportes_aviso'] ?? [];
        unset($_SESSION['editor_reportes_aviso']);

        try {
            $preguntas = $this->editorModel->obtenerPreguntasReportadas();

        } catch (PDOException $error) {

            error_log('Error al consultar reportes: ' . $error->getMessage());

            http_response_code(500);

            $this->render->renderiza('editorReportes', [
                'mensajeError' => 'No se pudieron cargar los reportes.'
            ]);

            return;
        }

        $this->render->renderiza('editorReportes', [
            'preguntas' => $preguntas,
            'sinReportes' => empty($preguntas),
            'mensajeExito' => $aviso['exito'] ?? null,
            'mensajeError' => $aviso['error'] ?? null
        ]);
    }

    public function detalleReporte(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        if ($id === false) {
            http_response_code(400);
            return;
        }

        try {
            $pregunta = $this->editorModel->obtenerDetallePreguntaReportada($id);

        } catch (PDOException $error) {

            error_log('Error al consultar detalle del reporte: ' . $error->getMessage());

            http_response_code(500);
            return;
        }

        if ($pregunta === null) {
            http_response_code(404);
            return;
        }

        $_SESSION['editor_reportes_csrf'] ??= bin2hex(random_bytes(32));

        $pendientes = array_filter(
            $pregunta['reportes'],
            static fn ($reporte) => $reporte['estado'] === 'pendiente'
        );

        $this->render->renderiza('editorDetalleReporte', [
            'pregunta' => $pregunta,
            'opciones' => $pregunta['opciones'],
            'reportes' => $pregunta['reportes'],
            'sinReportes' => empty($pregunta['reportes']),
            'sinPendientes' => empty($pendientes),
            'tokenCSRF' => $_SESSION['editor_reportes_csrf']
        ]);
    }

    public function resolverReporte(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        $token = Request::post('csrf_token');
        $tokenGuardado = $_SESSION['editor_reportes_csrf'] ?? null;

        if (
            !is_string($token) ||
            !is_string($tokenGuardado) ||
            !hash_equals($tokenGuardado, $token)
        ) {
            http_response_code(403);
            echo 'Volvé a abrir el detalle del reporte para realizar esta acción.';
            return;
        }

        $preguntaId = filter_var(
            Request::post('pregunta_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $decision = Request::post('decision');

        try {
            if ($preguntaId === false) {
                throw new DomainException('La pregunta indicada no es válida.');
            }

            if (!in_array($decision, ['mantener', 'baja'], true)) {
                throw new DomainException('La decisión indicada no es válida.');
            }

            $editorId = (int) ($_SESSION['usuario_id'] ?? 0);

            if ($editorId <= 0) {
                throw new DomainException('No se pudo identificar al editor.');
            }

            $mantener = $decision === 'mantener';

            $this->editorModel->resolverReporte(
                $preguntaId,
                $editorId,
                $mantener
            );

            $_SESSION['editor_reportes_aviso'] = [
                'exito' => $mantener
                    ? 'Pregunta aprobada. Seguirá disponible para jugar.'
                    : 'Pregunta dada de baja correctamente.'
            ];

        } catch (DomainException $error) {

            $_SESSION['editor_reportes_aviso'] = [
                'error' => $error->getMessage()
            ];

        } catch (PDOException $error) {

            error_log('Error al resolver reporte: ' . $error->getMessage());

            $_SESSION['editor_reportes_aviso'] = [
                'error' => 'No se pudo guardar la decisión. Volvé a intentar.'
            ];
        }

        header('Location: /editor/reportes', true, 303);
        exit;
    }
}
