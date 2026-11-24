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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        [$periodo, $datos] = $this->leerFiltroPeriodo('/admin/show');
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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        [$periodo, $datos] = $this->leerFiltroPeriodo('/admin/usuarios');
        $nombre = Request::get('buscar', '');
        $buscar = is_string($nombre) ? mb_substr(trim($nombre), 0, 50) : '';
        $idUsuario = max(0, (int) filter_var(Request::get('usuario'), FILTER_VALIDATE_INT));

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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            return;
        }

        header('Cache-Control: no-store');

        [$periodo, $datos] = $this->leerFiltroPeriodo('/admin/caracteristicas');

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

    private function leerFiltroPeriodo(string $ruta): array
    {
        $hoy = date('Y-m-d');
        try {
            $periodo = new PeriodoEstadisticoModel(
                Request::get('periodo', 'mes'),
                Request::get('fecha', $hoy),
                Request::get('detalle', '')
            );
        } catch (InvalidArgumentException $error) {
            $periodo = new PeriodoEstadisticoModel('mes', $hoy);
            $aviso = $error->getMessage() . ' Se muestra el mes actual.';
        }
        return [$periodo, $periodo->datosParaVista() + ['rutaFiltro' => $ruta, 'avisoFiltro' => $aviso ?? null]];
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
                $errorPreguntas = 'Para ver las estadísticas de preguntas, aplicá la migración hu24_fecha_preguntas.sql.';
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
