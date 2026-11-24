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

    public function obtenerCantidadJugadores(): array
    {
        $fila = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(activo = 1), 0) AS activos
             FROM usuarios
             WHERE rol = 'jugador'"
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) $fila['total'],
            'activos' => (int) $fila['activos'],
            'inactivos' => (int) $fila['total'] - (int) $fila['activos'],
        ];
    }

    public function obtenerCantidadPartidas(): array
    {
        $filas = $this->pdo->query(
            "SELECT estado, COUNT(*) AS cantidad FROM partidas GROUP BY estado"
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

    public function obtenerCantidadPreguntas(): array
    {
        $fila = $this->pdo->query(
            "SELECT COUNT(*) AS creadas,
                    COALESCE(SUM(p.habilitada = 1 AND c.habilitada = 1), 0) AS disponibles
             FROM preguntas p
             JOIN categorias c ON c.id = p.categoria_id"
        )->fetch(PDO::FETCH_ASSOC);

        $creadas = (int) $fila['creadas'];
        $disponibles = (int) $fila['disponibles'];

        return [
            'creadas' => $creadas,
            'disponibles' => $disponibles,
            'noDisponibles' => $creadas - $disponibles,
        ];
    }

    /** Preguntas disponibles agrupadas por categoría (incluye categorías sin preguntas). */
    public function obtenerCantidadPreguntasPorCategoria(): array
    {
        $filas = $this->pdo->query(
            "SELECT c.nombre,
                    COALESCE(SUM(p.habilitada = 1), 0) AS disponibles,
                    COUNT(p.id) AS creadas
             FROM categorias c
             LEFT JOIN preguntas p ON p.categoria_id = c.id
             WHERE c.habilitada = 1
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $fila['disponibles'] = (int) $fila['disponibles'];
            $fila['creadas'] = (int) $fila['creadas'];
        }
        unset($fila);

        return $filas;
    }

    public function obtenerCantidadUsuariosNuevosPorDia(int $dias): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT DATE(fecha_registro) AS dia, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador'
               AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(fecha_registro)"
        );
        $consulta->bindValue('dias', $dias - 1, PDO::PARAM_INT);
        $consulta->execute();
        $porDia = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-{$i} days"));
            $resultado[] = ['dia' => $dia, 'cantidad' => (int) ($porDia[$dia] ?? 0)];
        }

        return $resultado;
    }

    public function obtenerListaJugadores(): array
    {
        return $this->pdo->query(
            "SELECT id, nombre_usuario FROM usuarios WHERE rol = 'jugador' ORDER BY nombre_usuario"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDesempenoJugadores(string $buscar = '', int $idUsuario = 0): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT u.id, u.nombre_usuario, u.nombre_completo,
                    COUNT(pp.es_correcta) AS total_respuestas,
                    COALESCE(SUM(pp.es_correcta = 1), 0) AS correctas
             FROM usuarios u
             LEFT JOIN partidas pa ON pa.usuario_id = u.id
             LEFT JOIN partida_preguntas pp ON pp.partida_id = pa.id
             WHERE u.rol = 'jugador'
               AND (:id_usuario = 0 OR u.id = :id_usuario_igual)
               AND (:buscar_vacio = '' OR u.nombre_usuario LIKE :patron)
             GROUP BY u.id, u.nombre_usuario, u.nombre_completo
             ORDER BY u.nombre_usuario"
        );
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

    public function obtenerUsuariosPorPais(): array
    {
        $filas = $this->pdo->query(
            "SELECT MIN(TRIM(pais)) AS pais, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador'
             GROUP BY LOWER(TRIM(pais))
             ORDER BY cantidad DESC, pais"
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

    public function obtenerUsuariosPorSexo(): array
    {
        $filas = $this->pdo->query(
            "SELECT sexo, COUNT(*) AS cantidad
             FROM usuarios
             WHERE rol = 'jugador'
             GROUP BY sexo"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultado = [];
        foreach (['Masculino', 'Femenino', 'Prefiero no cargarlo'] as $sexo) {
            $resultado[] = ['sexo' => $sexo, 'cantidad' => (int) ($filas[$sexo] ?? 0)];
        }

        return $resultado;
    }

    public function obtenerUsuariosPorGrupoEdad(): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM((:anio_menores - anio_nacimiento) < :mayoria), 0) AS menores,
                COALESCE(SUM((:anio_jubilados - anio_nacimiento) >= :jubilacion), 0) AS jubilados,
                COUNT(*) AS total
             FROM usuarios
             WHERE rol = 'jugador'"
        );
        $anio = (int) date('Y');
        $consulta->bindValue('anio_menores', $anio, PDO::PARAM_INT);
        $consulta->bindValue('anio_jubilados', $anio, PDO::PARAM_INT);
        $consulta->bindValue('mayoria', self::EDAD_MAYORIA, PDO::PARAM_INT);
        $consulta->bindValue('jubilacion', self::EDAD_JUBILACION, PDO::PARAM_INT);
        $consulta->execute();
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
