<?php

namespace app\model;

class PreguntaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Recalcula la dificultad de las preguntas jugadas en una partida.
    // Menos de 10 respuestas: media | 80% o más: fácil | 40% a 79%: media | menos de 40%: difícil
    public function recalcularDificultadDePartida($partidaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "UPDATE preguntas p
                INNER JOIN (
                    SELECT pregunta_id, COUNT(*) AS total, SUM(es_correcta) AS aciertos
                    FROM partida_preguntas
                    WHERE fecha_respuesta IS NOT NULL
                      AND pregunta_id IN (
                          SELECT pregunta_id
                          FROM partida_preguntas
                          WHERE partida_id = :partida_id
                      )
                    GROUP BY pregunta_id
                ) s ON s.pregunta_id = p.id
                SET p.dificultad = CASE
                    WHEN s.total < 10 THEN 'media'
                    WHEN s.aciertos * 100 >= s.total * 80 THEN 'facil'
                    WHEN s.aciertos * 100 >= s.total * 40 THEN 'media'
                    ELSE 'dificil'
                END";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['partida_id' => $partidaId]);
    }
}