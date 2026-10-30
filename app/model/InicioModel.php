<?php

namespace app\model;

use PDO;

class InicioModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function buscarUsuarioPorId($id)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, nombre_completo, nombre_usuario, email
            FROM usuarios
            WHERE id = :id
            LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $id]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPuntajeAcumulado($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT COALESCE(SUM(puntaje), 0) AS total
                FROM partidas
                WHERE usuario_id = :id";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $idUsuario]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['total'] : 0;
    }

    public function obtenerPartidasJugadas($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT COUNT(*) AS total
                FROM partidas
                WHERE usuario_id = :id";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $idUsuario]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['total'] : 0;
    }

    public function obtenerHistorialPartidas($idUsuario, $limite = null)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT
                id AS numero,
                estado AS resultado,
                puntaje,
                DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') AS fecha
            FROM partidas
            WHERE usuario_id = :id
            ORDER BY fecha_creacion DESC, id DESC";

        if ($limite !== null) {
            $sql .= " LIMIT :limite";
        }

        $consulta = $pdo->prepare($sql);
        $consulta->bindValue(':id', $idUsuario, PDO::PARAM_INT);

        if ($limite !== null) {
            // LIMIT necesita un entero real, si no MySQL lo rechaza
            $consulta->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        }

        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
