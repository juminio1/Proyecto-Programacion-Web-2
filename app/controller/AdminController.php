<?php

namespace app\controller;

use app\model\AdminModel;
use app\model\PeriodoEstadisticoModel;
use InvalidArgumentException;
use PDOException;
use Request;

class AdminController
{
    private AdminModel $model;
    private $render;

    public function __construct(AdminModel $model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    public function show(): void
    {
        [$periodo, $datos] = $this->prepararFiltro('/admin/show');
        $datos['nombreAdmin'] = (string) ($_SESSION['usuario'] ?? 'Administrador');

        try {
            $datos += $this->cargarEstadisticas($periodo);
            $datos['estadisticasCargadas'] = true;
        } catch (PDOException $error) {
            error_log('Error al cargar estadísticas del administrador: ' . $error->getMessage());
            $datos['errorEstadisticas'] = 'No se pudieron cargar las estadísticas. Intentá nuevamente.';
        }

        $this->render->renderiza('admin', $datos);
    }

    public function usuarios(): void
    {
        [$periodo, $datos] = $this->prepararFiltro('/admin/usuarios');
        $buscar = $_SESSION['admin_buscar_jugador'] ?? '';
        $idUsuario = $_SESSION['admin_id_jugador'] ?? 0;

        $datos += [
            'vistaUsuarios' => true,
            'buscar' => $buscar,
            'hayFiltro' => $buscar !== '' || $idUsuario > 0,
        ];

        try {
            $lista = array_map(
                fn (array $j) => [
                    'id' => (int) $j['id'],
                    'nombre_usuario' => $j['nombre_usuario'],
                    'seleccionado' => (int) $j['id'] === $idUsuario,
                ],
                $this->model->obtenerListaJugadores($periodo)
            );
            $jugadores = $this->model->obtenerDesempenoJugadores($periodo, $buscar, $idUsuario);

            $datos['listaJugadores'] = $lista;
            $datos['jugadores'] = $jugadores;
            $datos['cantidadJugadores'] = count($jugadores);
            $datos['sinResultados'] = $jugadores === [];
            $datos['sinRespuestas'] = $jugadores !== [] && array_sum(array_column($jugadores, 'totalRespuestas')) === 0;
            $datos['usuariosCargados'] = true;
        } catch (PDOException $error) {
            error_log('Error al cargar desempeño de jugadores: ' . $error->getMessage());
            $datos['errorUsuarios'] = 'No se pudo cargar el desempeño de los jugadores. Intentá nuevamente.';
        }

        $this->render->renderiza('adminUsuarios', $datos);
    }

    public function caracteristicas(): void
    {
        [$periodo, $datos] = $this->prepararFiltro('/admin/caracteristicas');

        try {
            $paises = $this->model->obtenerUsuariosPorPais($periodo);
            $sexos = $this->model->obtenerUsuariosPorSexo($periodo);
            $edades = $this->model->obtenerUsuariosPorGrupoEdad($periodo);
            $total = array_sum(array_column($sexos, 'cantidad'));

            $paisesGrafico = array_slice($paises, 0, 8);
            $otros = array_sum(array_column(array_slice($paises, 8), 'cantidad'));
            if ($otros > 0) {
                $paisesGrafico[] = ['pais' => 'Otros', 'cantidad' => $otros];
            }

            $conPorcentaje = fn (array $filas) => array_map(
                fn (array $f) => $f + [
                        'porcentaje' => $total > 0 ? number_format($f['cantidad'] * 100 / $total, 1, ',', '') . ' %' : '—',
                    ],
                $filas
            );

            $graficos = [
                'paises' => [
                    'etiquetas' => array_column($paisesGrafico, 'pais'),
                    'valores' => array_column($paisesGrafico, 'cantidad'),
                ],
                'sexos' => [
                    'etiquetas' => array_column($sexos, 'sexo'),
                    'valores' => array_column($sexos, 'cantidad'),
                ],
                'edades' => [
                    'etiquetas' => array_column($edades, 'grupo'),
                    'valores' => array_column($edades, 'cantidad'),
                ],
            ];

            $datos['totalJugadores'] = $total;
            $datos['sinJugadores'] = $total === 0;
            $datos['paises'] = $conPorcentaje($paises);
            $datos['sexos'] = $conPorcentaje($sexos);
            $datos['edades'] = $conPorcentaje($edades);
            $datos['graficosJson'] = json_encode($graficos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $datos['caracteristicasCargadas'] = true;
        } catch (PDOException $error) {
            error_log('Error al cargar características de los jugadores: ' . $error->getMessage());
            $datos['errorCaracteristicas'] = 'No se pudieron cargar las estadísticas de los jugadores. Intentá nuevamente.';
        }

        $this->render->renderiza('adminCaracteristicas', $datos);
    }

    private function prepararFiltro(string $ruta): array
    {
        header('Cache-Control: no-store');
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($metodo, ['GET', 'POST'], true)) {
            http_response_code(405);
            header('Allow: GET, POST');
            exit;
        }

        $_SESSION['admin_filtro_csrf'] ??= bin2hex(random_bytes(32));
        if ($metodo === 'POST') {
            $this->guardarFiltro($ruta);
            header('Location: ' . $ruta, true, 303);
            exit;
        }

        if (isset($_GET['periodo']) || isset($_GET['fecha']) || isset($_GET['detalle'])
            || isset($_GET['buscar']) || isset($_GET['usuario'])) {
            header('Location: ' . $ruta, true, 302);
            exit;
        }

        $filtro = $_SESSION['admin_periodo'] ?? [];
        $periodo = new PeriodoEstadisticoModel(
            $filtro['periodo'] ?? 'general',
            $filtro['fecha'] ?? date('Y-m-d'),
            $filtro['detalle'] ?? ''
        );
        $aviso = $_SESSION['admin_aviso_filtro'] ?? null;
        unset($_SESSION['admin_aviso_filtro']);

        return [$periodo, $periodo->datosParaVista() + [
                'rutaFiltro' => $ruta,
                'avisoFiltro' => $aviso,
                'tokenFiltro' => $_SESSION['admin_filtro_csrf'],
            ]];
    }

    private function guardarFiltro(string $ruta): void
    {
        $token = Request::post('csrf_token');
        if (!is_string($token) || !hash_equals($_SESSION['admin_filtro_csrf'], $token)) {
            http_response_code(403);
            exit;
        }

        if ($ruta === '/admin/usuarios' && Request::post('limpiar') === '1') {
            unset($_SESSION['admin_buscar_jugador'], $_SESSION['admin_id_jugador']);
            return;
        }

        try {
            $periodo = new PeriodoEstadisticoModel(
                Request::post('periodo'),
                Request::post('fecha', date('Y-m-d')),
                Request::post('detalle', '')
            );
            $datos = $periodo->datosParaVista();
            $_SESSION['admin_periodo'] = [
                'periodo' => $datos['periodo'],
                'fecha' => $datos['fecha'],
                'detalle' => $datos['detalle'],
            ];

            if ($ruta === '/admin/usuarios') {
                $nombre = Request::post('buscar', '');
                $_SESSION['admin_buscar_jugador'] = is_string($nombre) ? mb_substr(trim($nombre), 0, 50) : '';
                $_SESSION['admin_id_jugador'] = max(0, (int) filter_var(Request::post('usuario'), FILTER_VALIDATE_INT));
            }
        } catch (InvalidArgumentException $error) {
            $_SESSION['admin_aviso_filtro'] = $error->getMessage() . ' Se conserva el filtro aplicado.';
        }
    }

    private function cargarEstadisticas(PeriodoEstadisticoModel $periodo): array
    {
        $jugadores = $this->model->obtenerCantidadJugadores($periodo);
        $partidas = $this->model->obtenerCantidadPartidas($periodo);
        $errorPreguntas = null;
        try {
            $porCategoria = $this->model->obtenerCantidadPreguntasPorCategoria($periodo);
        } catch (PDOException $error) {
            error_log('Error al cargar estadísticas de preguntas: ' . $error->getMessage());
            $porCategoria = [];
            $errorPreguntas = 'No se pudieron cargar las estadísticas de preguntas.';
            if (($error->errorInfo[0] ?? null) === '42S22'
                && str_contains($error->errorInfo[2] ?? '', 'p.fecha_creacion')) {
                $errorPreguntas = 'Para ver las estadísticas de preguntas, aplicá la migración fecha_preguntas.sql.';
            }
        }
        $preguntas = [
            'creadas' => array_sum(array_column($porCategoria, 'creadas')),
            'disponibles' => array_sum(array_column($porCategoria, 'disponibles')),
        ];
        $nuevosPorDia = $this->model->obtenerCantidadUsuariosNuevosPorDia($periodo);

        $totalNuevos = array_sum(array_column($nuevosPorDia, 'cantidad'));

        // Datos para los gráficos (Chart.js).
        $graficos = [
            'jugadores' => [
                'etiquetas' => ['Activos', 'Sin activar'],
                'valores' => [$jugadores['activos'], $jugadores['inactivos']],
            ],
            'partidas' => [
                'etiquetas' => ['Finalizadas', 'Abandonadas', 'En juego'],
                'valores' => [$partidas['finalizadas'], $partidas['abandonadas'], $partidas['enJuego']],
            ],
            'preguntas' => [
                'etiquetas' => array_column($porCategoria, 'nombre'),
                'valores' => array_column($porCategoria, 'disponibles'),
            ],
            'nuevos' => [
                'etiquetas' => array_map(fn ($f) => date('d/m', strtotime($f['dia'])), $nuevosPorDia),
                'valores' => array_column($nuevosPorDia, 'cantidad'),
            ],
        ];

        return [
            'jugadores' => $jugadores,
            'partidas' => $partidas,
            'preguntas' => $preguntas,
            'preguntasPorCategoria' => $porCategoria,
            'errorPreguntas' => $errorPreguntas,
            'sinPreguntas' => $porCategoria === [],
            'sinActividad' => $errorPreguntas === null && $jugadores['total'] === 0
                && $partidas['total'] === 0 && $porCategoria === [],
            'usuariosNuevos' => $totalNuevos,
            // Solo se listan en la tabla los días con altas (el gráfico muestra todos).
            'nuevosPorDia' => array_values(array_map(
                fn ($f) => ['dia' => date('d/m/Y', strtotime($f['dia'])), 'cantidad' => $f['cantidad']],
                array_filter($nuevosPorDia, fn ($f) => $f['cantidad'] > 0)
            )),
            'sinNuevos' => $totalNuevos === 0,
            'graficosJson' => json_encode($graficos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
