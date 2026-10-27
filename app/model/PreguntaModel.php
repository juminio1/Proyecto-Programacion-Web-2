<?php

namespace app\model;

use PDO;

class PreguntaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerPreguntas()
    {
        $sql = "SELECT
                    p.id,
                    p.enunciado,
                    c.nombre AS categoria,
                    c.color
                FROM preguntas p
                INNER JOIN categorias c
                    ON p.categoria_id = c.id
                ORDER BY p.id ASC";

        return $this->database->queryAll($sql);
    }

    public function obtenerPreguntaPorId($id)
    {
        $sql = "SELECT
                    p.id,
                    p.enunciado,
                    c.nombre AS categoria,
                    c.color
                FROM preguntas p
                INNER JOIN categorias c
                    ON p.categoria_id = c.id
                WHERE p.id = " . (int) $id;

        return $this->database->queryOne($sql);
    }
}
