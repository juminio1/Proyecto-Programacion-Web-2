<?php

namespace app\model;

use PDO;

class UsuarioModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerNivel($idUsuario)
    {
        $pdo = $this->database->getConexion();
        $consulta = $pdo->prepare("SELECT nivel FROM usuarios WHERE id = :id LIMIT 1");
        $consulta->execute(['id' => $idUsuario]);
        return $consulta->fetchColumn() ?: 'medio';
    }

    // Usa las últimas 10 respuestas del jugador (ya incluye las de la partida que cierra)
    public function recalcularNivel($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT pp.es_correcta
                FROM partida_preguntas pp
                INNER JOIN partidas pa ON pa.id = pp.partida_id
                WHERE pa.usuario_id = :usuario_id
                  AND pp.fecha_respuesta IS NOT NULL
                ORDER BY pp.fecha_respuesta DESC, pp.id DESC
                LIMIT 10";
        $consulta = $pdo->prepare($sql);
        $consulta->execute(['usuario_id' => $idUsuario]);
        $filas = $consulta->fetchAll(PDO::FETCH_COLUMN);

        if (empty($filas)) {
            return;
        }

        $porcentaje = (array_sum($filas) / count($filas)) * 100;
        $nivel = $porcentaje >= 70 ? 'alto' : ($porcentaje >= 40 ? 'medio' : 'bajo');

        $upd = $pdo->prepare("UPDATE usuarios SET nivel = :nivel WHERE id = :id");
        $upd->execute(['nivel' => $nivel, 'id' => $idUsuario]);
    }
}