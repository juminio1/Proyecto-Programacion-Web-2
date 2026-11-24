<?php

namespace app\model;

use PDO;

class AdminModel
{
    public const EDAD_MAYORIA = 18;
    public const EDAD_JUBILACION = 65;
    private PDO $pdo;

    public function __construct($database)
    {
        $this->pdo = $database->getConexion();
    }

    private function filtroFecha(PeriodoEstadisticoModel $periodo, string $columna): string
    {
        return $periodo->esGeneral() ? '' : "AND {$columna} >= :desde AND {$columna} < :hasta";
    }

    private function consultarPeriodo(string $sql, PeriodoEstadisticoModel $periodo): \PDOStatement
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($periodo->esGeneral() ? [] : $periodo->parametrosSql());
        return $consulta;
    }

    public function obtenerCantidadJugadores(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'fecha_registro');
        $fila = $this->consultarPeriodo(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(activo = 1), 0) AS activos
             FROM usuarios
             WHERE rol = 'jugador' $filtro", $periodo
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) $fila['total'],
            'activos' => (int) $fila['activos'],
            'inactivos' => (int) $fila['total'] - (int) $fila['activos'],
        ];
    }

    public function obtenerCantidadPartidas(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'fecha_creacion');
        $filas = $this->consultarPeriodo(
            "SELECT estado, COUNT(*) AS cantidad FROM partidas
             WHERE 1 = 1 $filtro
             GROUP BY estado", $periodo
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $finalizadas = (int) ($filas['finalizada'] ?? 0);
        $abandonadas = (int) ($filas['abandonada'] ?? 0);
        $enJuego = (int) ($filas['creada'] ?? 0) + (int) ($filas['en_curso'] ?? 0);

        return [
            'jugadas' => $finalizadas + $abandonadas,
            'finalizadas' => $finalizadas,
            'abandonadas' => $abandonadas,
            'enJuego' => $enJuego,
            'total' => $finalizadas + $abandonadas + $enJuego,
        ];
    }

    public function obtenerCantidadPreguntasPorCategoria(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'p.fecha_creacion');
        $filas = $this->consultarPeriodo(
            "SELECT c.nombre,
                    COALESCE(SUM(p.habilitada = 1 AND c.habilitada = 1), 0) AS disponibles,
                    COUNT(p.id) AS creadas
             FROM categorias c
             JOIN preguntas p ON p.categoria_id = c.id
             WHERE 1 = 1 $filtro
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre", $periodo
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['disponibles'] = (int) $fila['disponibles'];
            $fila['creadas'] = (int) $fila['creadas'];
        }
        unset($fila);

        return $filas;
    }

    public function obtenerCantidadUsuariosNuevosPorDia(PeriodoEstadisticoModel $periodo): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT DATE(fecha_registro) AS dia, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador' AND fecha_registro >= :desde AND fecha_registro < :hasta
             GROUP BY DATE(fecha_registro)"
        );
        $consulta->execute($periodo->parametrosSql());
        $porDia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultado = [];
        for ($dia = $periodo->desde; $dia < $periodo->hasta; $dia = $dia->modify('+1 day')) {
            $fecha = $dia->format('Y-m-d');
            $resultado[] = ['dia' => $fecha, 'cantidad' => (int) ($porDia[$fecha] ?? 0)];
        }
        return $resultado;
    }

    public function obtenerListaJugadores(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $periodo->esGeneral() ? '' : 'AND (fecha_registro < :hasta OR fecha_registro IS NULL)';
        $consulta = $this->pdo->prepare(
            "SELECT id, nombre_usuario FROM usuarios
             WHERE rol = 'jugador' $filtro
             ORDER BY nombre_usuario"
        );
        $consulta->execute($periodo->esGeneral() ? [] : ['hasta' => $periodo->hasta->format('Y-m-d')]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDesempenoJugadores(PeriodoEstadisticoModel $periodo, string $buscar = '', int $idUsuario = 0): array
    {
        $filtroRespuesta = $this->filtroFecha($periodo, 'pp.fecha_respuesta');
        $filtroRegistro = $periodo->esGeneral() ? '' : 'AND (u.fecha_registro < :fin_registro OR u.fecha_registro IS NULL)';
        $consulta = $this->pdo->prepare(
            "SELECT u.id, u.nombre_usuario, u.nombre_completo,
                    COUNT(pp.es_correcta) AS total_respuestas,
                    COALESCE(SUM(pp.es_correcta = 1), 0) AS correctas
             FROM usuarios u
             LEFT JOIN partidas pa ON pa.usuario_id = u.id
             LEFT JOIN partida_preguntas pp ON pp.partida_id = pa.id
                 $filtroRespuesta
             WHERE u.rol = 'jugador' $filtroRegistro
               AND (:id_usuario = 0 OR u.id = :id_usuario_igual)
               AND (:buscar_vacio = '' OR u.nombre_usuario LIKE :patron)
             GROUP BY u.id, u.nombre_usuario, u.nombre_completo
             ORDER BY u.nombre_usuario"
        );
        if (!$periodo->esGeneral()) {
            foreach ($periodo->parametrosSql() as $clave => $fecha) {
                $consulta->bindValue($clave, $fecha);
            }
            $consulta->bindValue('fin_registro', $periodo->hasta->format('Y-m-d'));
        }
        $consulta->bindValue('id_usuario', $idUsuario, PDO::PARAM_INT);
        $consulta->bindValue('id_usuario_igual', $idUsuario, PDO::PARAM_INT);
        $consulta->bindValue('buscar_vacio', $buscar);
        // Se escapan % y _ para que se busquen como texto común.
        $consulta->bindValue('patron', '%' . addcslashes($buscar, '%_\\') . '%');
        $consulta->execute();

        $jugadores = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $total = (int) $fila['total_respuestas'];
            $correctas = (int) $fila['correctas'];
            $porcentaje = $total > 0 ? round($correctas * 100 / $total, 1) : null;

            $jugadores[] = [
                'id' => (int) $fila['id'],
                'nombre_usuario' => $fila['nombre_usuario'],
                'nombre_completo' => $fila['nombre_completo'],
                'totalRespuestas' => $total,
                'correctas' => $correctas,
                'incorrectas' => $total - $correctas,
                'tieneRespuestas' => $total > 0,
                'porcentajeTexto' => $porcentaje === null ? null : number_format($porcentaje, 1, ',', '') . ' %',
                'porcentajeAncho' => $porcentaje === null ? 0 : $porcentaje,
            ];
        }

        return $jugadores;
    }

    public function obtenerUsuariosPorPais(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'fecha_registro');
        $filas = $this->consultarPeriodo(
            "SELECT MIN(TRIM(pais)) AS pais, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador' $filtro
             GROUP BY LOWER(TRIM(pais))
             ORDER BY cantidad DESC, pais", $periodo
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['cantidad'] = (int) $fila['cantidad'];
            if ($fila['pais'] === null || $fila['pais'] === '') {
                $fila['pais'] = 'Sin especificar';
            }
        }
        unset($fila);

        return $filas;
    }

    public function obtenerUsuariosPorSexo(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'fecha_registro');
        $filas = $this->consultarPeriodo(
            "SELECT sexo, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador' $filtro
             GROUP BY sexo", $periodo
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultado = [];
        foreach (['Masculino', 'Femenino', 'Prefiero no cargarlo'] as $sexo) {
            $resultado[] = ['sexo' => $sexo, 'cantidad' => (int) ($filas[$sexo] ?? 0)];
        }

        return $resultado;
    }

    public function obtenerUsuariosPorGrupoEdad(PeriodoEstadisticoModel $periodo): array
    {
        $filtro = $this->filtroFecha($periodo, 'fecha_registro');
        $anio = $periodo->esGeneral() ? 'YEAR(CURDATE())' : 'YEAR(fecha_registro)';
        $consulta = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM($anio - anio_nacimiento < :mayoria), 0) AS menores,
                COALESCE(SUM($anio - anio_nacimiento >= :jubilacion), 0) AS jubilados,
                COUNT(*) AS total
             FROM usuarios
             WHERE rol = 'jugador' $filtro"
        );
        $parametros = $periodo->esGeneral() ? [] : $periodo->parametrosSql();
        $consulta->execute($parametros + [
                'mayoria' => self::EDAD_MAYORIA, 'jubilacion' => self::EDAD_JUBILACION,
            ]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        $menores = (int) $fila['menores'];
        $jubilados = (int) $fila['jubilados'];
        $medio = (int) $fila['total'] - $menores - $jubilados;

        return [
            ['grupo' => 'Menores', 'rango' => 'menos de ' . self::EDAD_MAYORIA . ' años', 'cantidad' => $menores],
            ['grupo' => 'Medio', 'rango' => 'de ' . self::EDAD_MAYORIA . ' a ' . (self::EDAD_JUBILACION - 1) . ' años', 'cantidad' => $medio],
            ['grupo' => 'Jubilados', 'rango' => self::EDAD_JUBILACION . ' años o más', 'cantidad' => $jubilados],
        ];
    }

}
