<?php

namespace app\model;

use PDO;

class RankingModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerRankingSemanal()
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT
                u.id,
                u.nombre_usuario,
                u.nombre_completo,
                COALESCE(SUM(p.puntaje), 0) AS puntaje
            FROM usuarios u
            LEFT JOIN partidas p
                ON p.usuario_id = u.id
                AND p.estado <> :abandonada
                AND p.fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                AND p.fecha_creacion < DATE_ADD(
                    DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
                    INTERVAL 7 DAY
                )
            GROUP BY u.id, u.nombre_usuario, u.nombre_completo
            ORDER BY puntaje DESC, u.nombre_usuario ASC";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['abandonada' => 'abandonada']);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}