<?php

namespace app\controller;

use app\model\AdminModel;
use PDOException;
use Request;

class AdminController
{
    private const PERIODOS = [7, 30, 90];
    private const PERIODO_POR_DEFECTO = 30;

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

        $periodo = (int) Request::get('periodo', self::PERIODO_POR_DEFECTO);
        if (!in_array($periodo, self::PERIODOS, true)) {
            $periodo = self::PERIODO_POR_DEFECTO;
        }

        $datos = [
            'nombreAdmin' => (string) ($_SESSION['usuario'] ?? 'Administrador'),
            'periodo' => $periodo,
            'periodos' => array_map(
                fn (int $dias) => ['dias' => $dias, 'activo' => $dias === $periodo],
                self::PERIODOS
            ),
        ];

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

        $buscar = trim((string) Request::get('buscar', ''));
        $buscar = mb_substr($buscar, 0, 50);
        $idUsuario = max(0, (int) Request::get('usuario', 0));

        $datos = [
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
                $this->model->obtenerListaJugadores()
            );
            $jugadores = $this->model->obtenerDesempenoJugadores($buscar, $idUsuario);

            $datos['listaJugadores'] = $lista;
            $datos['jugadores'] = $jugadores;
            $datos['cantidadJugadores'] = count($jugadores);
            $datos['sinResultados'] = $jugadores === [];
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

        $datos = [];

        try {
            $paises = $this->model->obtenerUsuariosPorPais();
            $sexos = $this->model->obtenerUsuariosPorSexo();
            $edades = $this->model->obtenerUsuariosPorGrupoEdad();
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

    private function cargarEstadisticas(int $periodo): array
    {
        $jugadores = $this->model->obtenerCantidadJugadores();
        $partidas = $this->model->obtenerCantidadPartidas();
        $preguntas = $this->model->obtenerCantidadPreguntas();
        $porCategoria = $this->model->obtenerCantidadPreguntasPorCategoria();
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
            'sinCategorias' => $porCategoria === [],
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
