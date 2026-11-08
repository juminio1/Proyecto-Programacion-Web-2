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
                       nivel
                FROM usuarios
                WHERE id = :id
                LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $idUsuario]);

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        return $usuario !== false ? $usuario : null;
    }

    public function obtenerPartidasFinalizadas(int $idUsuario): array
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT
                    id AS numero,
                    estado AS resultado,
                    puntaje,
                    DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') AS fecha
                FROM partidas
                WHERE usuario_id = :id
                  AND estado = :estado
                ORDER BY fecha_creacion DESC, id DESC";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'id' => $idUsuario,
            'estado' => EstadoPartida::FINALIZADA->value
        ]);

        $partidas = $consulta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($partidas as &$partida) {
            $partida['resultado'] = ucfirst((string) $partida['resultado']);
        }
        unset($partida);

        return $partidas;
    }

    public function calcularTotalPartidas(array $partidasFinalizadas): int
    {
        return count($partidasFinalizadas);
    }

    public function calcularPuntajeAcumulado(array $partidasFinalizadas): int
    {
        $total = 0;
        foreach ($partidasFinalizadas as $partida) {
            $total += (int) ($partida['puntaje'] ?? 0);
        }
        return $total;
    }
}
