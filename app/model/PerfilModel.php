<?php

namespace app\model;

use app\model\enums\EstadoPartida;
use PDO;

class PerfilModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerUsuarioPorId(int $idUsuario): ?array
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id,
                       nombre_completo,
                       anio_nacimiento,
                       sexo,
                       pais,
                       ciudad,
                       latitud,
                       longitud,
                       email,
                       nombre_usuario,
                       foto_perfil,
                       fecha_registro,
                       nivel,
                       perfil_publico
                FROM usuarios
                WHERE id = :id
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'id' => $idUsuario
        ]);

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        return $usuario !== false
            ? $usuario
            : null;
    }


    public function obtenerResumenPartidasFinalizadas(
        int $idUsuario
    ): array {
        $pdo = $this->database->getConexion();

        $sql = "SELECT
                    COUNT(*) AS total_partidas,
                    COALESCE(SUM(puntaje), 0) AS puntaje_acumulado
                FROM partidas
                WHERE usuario_id = :id
                  AND estado = :estado";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'id' => $idUsuario,
            'estado' => EstadoPartida::FINALIZADA->value
        ]);

        $resumen =
            $consulta->fetch(PDO::FETCH_ASSOC)
                ?: [];

        return [
            'totalPartidas' =>
                (int) ($resumen['total_partidas'] ?? 0),

            'puntajeAcumulado' =>
                (int) ($resumen['puntaje_acumulado'] ?? 0)
        ];
    }


    public function obtenerPartidasFinalizadas(
        int $idUsuario
    ): array {
        $pdo = $this->database->getConexion();

        $sql = "SELECT
                    id AS numero,
                    estado AS resultado,
                    puntaje,
                    DATE_FORMAT(
                        fecha_creacion,
                        '%d/%m/%Y %H:%i'
                    ) AS fecha
                FROM partidas
                WHERE usuario_id = :id
                  AND estado = :estado
                ORDER BY fecha_creacion DESC, id DESC";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'id' => $idUsuario,
            'estado' => EstadoPartida::FINALIZADA->value
        ]);

        $partidas =
            $consulta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($partidas as &$partida) {
            $partida['resultado'] =
                ucfirst(
                    (string) $partida['resultado']
                );
        }

        unset($partida);

        return $partidas;
    }


    public function actualizarPrivacidadPerfil(
        int $idUsuario,
        bool $perfilPublico
    ): void {
        $pdo = $this->database->getConexion();

        $consulta = $pdo->prepare(
            "UPDATE usuarios
             SET perfil_publico = :perfil_publico
             WHERE id = :id"
        );

        $consulta->execute([
            'perfil_publico' =>
                $perfilPublico ? 1 : 0,

            'id' =>
                $idUsuario
        ]);
    }
}