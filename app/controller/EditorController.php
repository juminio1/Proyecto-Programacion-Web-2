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

        $this->render->renderiza('editor');
    }

    public function mostrarGestionPreguntas(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        try {
            $preguntas = $this->editorModel
                ->obtenerTodasLasPreguntasConCategoria();

            $categoriasConPreguntas =
                $this->agruparPreguntasPorCategoria($preguntas);

            $this->render->renderiza('editorPreguntas', [
                'categoriasConPreguntas' => $categoriasConPreguntas,
                'sinPreguntas' => empty($preguntas)
            ]);

        } catch (PDOException $error) {

            error_log(
                'Error al cargar preguntas para gestión: '
                . $error->getMessage()
            );

            $this->render->renderiza('editorPreguntas', [
                'categoriasConPreguntas' => [],
                'sinPreguntas' => true,
                'mensajeError' =>
                    'No se pudieron cargar las preguntas. Intentá nuevamente.'
            ]);
        }
    }

    private function agruparPreguntasPorCategoria(array $preguntas): array
    {
        $categorias = [];

        foreach ($preguntas as $pregunta) {

            $categoriaId = (int) $pregunta['categoria_id'];

            if (!isset($categorias[$categoriaId])) {
                $categorias[$categoriaId] = [
                    'nombreCategoria' => $pregunta['categoria'],
                    'colorCategoria' => $pregunta['color_categoria'],
                    'cantidadPreguntas' => 0,
                    'preguntas' => []
                ];
            }

            $pregunta['estado'] =
                (int) $pregunta['habilitada'] === 1
                    ? 'Habilitada'
                    : 'Deshabilitada';

            $pregunta['estaHabilitada'] =
                (int) $pregunta['habilitada'] === 1;

            $pregunta['estaDeshabilitada'] =
                (int) $pregunta['habilitada'] === 0;

            $pregunta['dificultadTexto'] =
                ucfirst($pregunta['dificultad']);

            $pregunta['dificultadFacil'] =
                $pregunta['dificultad'] === 'facil';

            $pregunta['dificultadMedia'] =
                $pregunta['dificultad'] === 'media';

            $pregunta['dificultadDificil'] =
                $pregunta['dificultad'] === 'dificil';

            $categorias[$categoriaId]['preguntas'][] = $pregunta;

            $categorias[$categoriaId]['cantidadPreguntas']++;
        }

        return array_values($categorias);
    }

    public function mostrarFormularioNuevaPregunta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        $_SESSION['editor_nueva_pregunta_csrf'] ??= bin2hex(random_bytes(32));

        try {
            $categorias = $this->sugerencias->obtenerCategorias();

            $this->render->renderiza('editorNuevaPregunta', [
                'categorias' => $categorias,
                'tokenCSRF' => $_SESSION['editor_nueva_pregunta_csrf']
            ]);

        } catch (PDOException $error) {
            error_log(
                'Error al cargar categorías para nueva pregunta: '
                . $error->getMessage()
            );

            $this->render->renderiza('editorNuevaPregunta', [
                'categorias' => [],
                'tokenCSRF' => $_SESSION['editor_nueva_pregunta_csrf'],
                'hay_errores' => true,
                'errores' => [
                    'No se pudieron cargar las categorías.'
                ]
            ]);
        }
    }

    public function crearNuevaPregunta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        $token = Request::post('csrf_token');
        $tokenGuardado = $_SESSION['editor_nueva_pregunta_csrf'] ?? null;

        if (
            !is_string($token)
            || !is_string($tokenGuardado)
            || !hash_equals($tokenGuardado, $token)
        ) {
            http_response_code(403);
            echo 'Volvé a abrir el formulario para crear la pregunta.';
            return;
        }

        $pregunta = trim((string) Request::post('pregunta', ''));
        $categoriaId = (int) Request::post('categoria_id', 0);

        $opcionA = trim((string) Request::post('opcion_a', ''));
        $opcionB = trim((string) Request::post('opcion_b', ''));
        $opcionC = trim((string) Request::post('opcion_c', ''));
        $opcionD = trim((string) Request::post('opcion_d', ''));

        $respuestaCorrecta = strtoupper(
            trim((string) Request::post('respuesta_correcta', ''))
        );

        try {
            $this->editorModel->crearPreguntaConOpciones(
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );

            // Renovamos el token después de una creación exitosa.
            $_SESSION['editor_nueva_pregunta_csrf'] = bin2hex(random_bytes(32));

            $categorias = $this->sugerencias->obtenerCategorias();

            $this->render->renderiza('editorNuevaPregunta', [
                'categorias' => $categorias,
                'tokenCSRF' => $_SESSION['editor_nueva_pregunta_csrf'],
                'exito' => true,
                'mensaje_exito' => 'La pregunta fue creada correctamente y ya está disponible para jugar.'
            ]);

        } catch (DomainException $error) {

            $this->mostrarFormularioNuevaPreguntaConError(
                $error->getMessage(),
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );

        } catch (PDOException $error) {

            error_log(
                'Error al crear nueva pregunta: '
                . $error->getMessage()
            );

            $this->mostrarFormularioNuevaPreguntaConError(
                'No se pudo crear la pregunta. Intentá nuevamente.',
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );
        }
    }

    private function mostrarFormularioNuevaPreguntaConError(
        string $mensajeError,
        int $categoriaId,
        string $pregunta,
        string $opcionA,
        string $opcionB,
        string $opcionC,
        string $opcionD,
        string $respuestaCorrecta
    ): void {
        $categorias = $this->sugerencias->obtenerCategorias();

        foreach ($categorias as &$categoria) {
            $categoria['seleccionada'] =
                (int) $categoria['id'] === $categoriaId;
        }

        unset($categoria);

        $this->render->renderiza('editorNuevaPregunta', [
            'hay_errores' => true,
            'errores' => [$mensajeError],

            'categorias' => $categorias,
            'tokenCSRF' => $_SESSION['editor_nueva_pregunta_csrf'],

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
    }

    public function darDeBajaPregunta(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);

            echo json_encode([
                'exito' => false,
                'mensaje' => 'Método no permitido.'
            ]);

            return;
        }

        $preguntaId = (int) Request::post('pregunta_id', 0);

        if ($preguntaId <= 0) {
            http_response_code(400);

            echo json_encode([
                'exito' => false,
                'mensaje' => 'La pregunta seleccionada no es válida.'
            ]);

            return;
        }

        try {
            $preguntaFueDadaDeBaja =
                $this->editorModel->darDeBajaPreguntaPorId($preguntaId);

            if (!$preguntaFueDadaDeBaja) {
                http_response_code(404);

                echo json_encode([
                    'exito' => false,
                    'mensaje' => 'La pregunta no existe o ya se encuentra dada de baja.'
                ]);

                return;
            }

            echo json_encode([
                'exito' => true,
                'mensaje' => 'La pregunta fue dada de baja correctamente.'
            ]);

        } catch (DomainException $error) {
            http_response_code(400);

            echo json_encode([
                'exito' => false,
                'mensaje' => $error->getMessage()
            ]);

        } catch (PDOException $error) {
            error_log(
                'Error al dar de baja la pregunta: '
                . $error->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'exito' => false,
                'mensaje' => 'No se pudo dar de baja la pregunta.'
            ]);
        }
    }

    public function mostrarFormularioEditarPregunta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        $preguntaId = isset($_GET['id'])
            ? (int) $_GET['id']
            : 0;

        if ($preguntaId <= 0) {
            http_response_code(400);
            echo 'La pregunta seleccionada no es válida.';
            return;
        }

        $_SESSION['editor_editar_pregunta_csrf'] ??=
            bin2hex(random_bytes(32));

        try {
            $pregunta =
                $this->editorModel->obtenerPreguntaConOpcionesPorId(
                    $preguntaId
                );

            if ($pregunta === null) {
                http_response_code(404);
                echo 'La pregunta no existe.';
                return;
            }

            $categorias =
                $this->sugerencias->obtenerCategorias();

            foreach ($categorias as &$categoria) {
                $categoria['seleccionada'] =
                    (int) $categoria['id']
                    === (int) $pregunta['categoria_id'];
            }

            unset($categoria);

            $respuestaCorrecta =
                strtoupper(
                    $pregunta['respuesta_correcta'] ?? ''
                );

            $this->render->renderiza(
                'editorEditarPregunta',
                [
                    'preguntaId' => $pregunta['id'],
                    'pregunta' => $pregunta['pregunta'],

                    'categorias' => $categorias,

                    'opcion_a' => $pregunta['opcion_a'] ?? '',
                    'opcion_b' => $pregunta['opcion_b'] ?? '',
                    'opcion_c' => $pregunta['opcion_c'] ?? '',
                    'opcion_d' => $pregunta['opcion_d'] ?? '',

                    'correcta_a' => $respuestaCorrecta === 'A',
                    'correcta_b' => $respuestaCorrecta === 'B',
                    'correcta_c' => $respuestaCorrecta === 'C',
                    'correcta_d' => $respuestaCorrecta === 'D',

                    'tokenCSRF' =>
                        $_SESSION['editor_editar_pregunta_csrf']
                ]
            );

        } catch (PDOException $error) {

            error_log(
                'Error al cargar pregunta para editar: '
                . $error->getMessage()
            );

            http_response_code(500);
            echo 'No se pudo cargar la pregunta.';
        }
    }

    public function guardarCambiosPregunta(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return;
        }

        $token = Request::post('csrf_token');

        $tokenGuardado =
            $_SESSION['editor_editar_pregunta_csrf'] ?? null;

        if (
            !is_string($token)
            || !is_string($tokenGuardado)
            || !hash_equals($tokenGuardado, $token)
        ) {
            http_response_code(403);
            echo 'Volvé a abrir el formulario para editar la pregunta.';
            return;
        }

        $preguntaId =
            (int) Request::post('pregunta_id', 0);

        $categoriaId =
            (int) Request::post('categoria_id', 0);

        $pregunta =
            trim((string) Request::post('pregunta', ''));

        $opcionA =
            trim((string) Request::post('opcion_a', ''));

        $opcionB =
            trim((string) Request::post('opcion_b', ''));

        $opcionC =
            trim((string) Request::post('opcion_c', ''));

        $opcionD =
            trim((string) Request::post('opcion_d', ''));

        $respuestaCorrecta = strtoupper(
            trim(
                (string) Request::post(
                    'respuesta_correcta',
                    ''
                )
            )
        );

        try {

            $this->editorModel->actualizarPreguntaConOpciones(
                $preguntaId,
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );

            $_SESSION['editor_editar_pregunta_csrf'] =
                bin2hex(random_bytes(32));

            $this->mostrarFormularioEditarPreguntaConResultado(
                $preguntaId,
                true,
                'La pregunta fue modificada correctamente.'
            );

        } catch (DomainException $error) {

            $this->mostrarFormularioEditarPreguntaConResultado(
                $preguntaId,
                false,
                $error->getMessage(),
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );

        } catch (PDOException $error) {

            error_log(
                'Error al modificar pregunta: '
                . $error->getMessage()
            );

            $this->mostrarFormularioEditarPreguntaConResultado(
                $preguntaId,
                false,
                'No se pudo modificar la pregunta. Intentá nuevamente.',
                $categoriaId,
                $pregunta,
                $opcionA,
                $opcionB,
                $opcionC,
                $opcionD,
                $respuestaCorrecta
            );
        }
    }

    private function mostrarFormularioEditarPreguntaConResultado(
        int $preguntaId,
        bool $exito,
        string $mensaje,
        ?int $categoriaId = null,
        ?string $pregunta = null,
        ?string $opcionA = null,
        ?string $opcionB = null,
        ?string $opcionC = null,
        ?string $opcionD = null,
        ?string $respuestaCorrecta = null
    ): void {

        $preguntaActual =
            $this->editorModel
                ->obtenerPreguntaConOpcionesPorId(
                    $preguntaId
                );

        if ($preguntaActual === null) {
            http_response_code(404);
            echo 'La pregunta no existe.';
            return;
        }

        $categorias =
            $this->sugerencias->obtenerCategorias();

        $categoriaSeleccionada =
            $categoriaId
            ?? (int) $preguntaActual['categoria_id'];

        foreach ($categorias as &$categoria) {

            $categoria['seleccionada'] =
                (int) $categoria['id']
                === $categoriaSeleccionada;
        }

        unset($categoria);

        if ($exito) {

            $pregunta =
                $preguntaActual['pregunta'];

            $opcionA =
                $preguntaActual['opcion_a'] ?? '';

            $opcionB =
                $preguntaActual['opcion_b'] ?? '';

            $opcionC =
                $preguntaActual['opcion_c'] ?? '';

            $opcionD =
                $preguntaActual['opcion_d'] ?? '';

            $respuestaCorrecta =
                strtoupper(
                    $preguntaActual['respuesta_correcta']
                    ?? ''
                );
        }

        $this->render->renderiza(
            'editorEditarPregunta',
            [
                'preguntaId' => $preguntaId,

                'pregunta' =>
                    $pregunta ?? '',

                'categorias' =>
                    $categorias,

                'opcion_a' =>
                    $opcionA ?? '',

                'opcion_b' =>
                    $opcionB ?? '',

                'opcion_c' =>
                    $opcionC ?? '',

                'opcion_d' =>
                    $opcionD ?? '',

                'correcta_a' =>
                    $respuestaCorrecta === 'A',

                'correcta_b' =>
                    $respuestaCorrecta === 'B',

                'correcta_c' =>
                    $respuestaCorrecta === 'C',

                'correcta_d' =>
                    $respuestaCorrecta === 'D',

                'tokenCSRF' =>
                    $_SESSION['editor_editar_pregunta_csrf'],

                'exito' =>
                    $exito,

                'mensaje_exito' =>
                    $exito ? $mensaje : null,

                'hay_errores' =>
                    !$exito,

                'errores' =>
                    !$exito
                        ? [$mensaje]
                        : []
            ]
        );
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
