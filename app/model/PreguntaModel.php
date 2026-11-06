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

    // Registra el reporte de una pregunta hecho por un jugador durante su partida.
    // Devuelve ['ok' => bool, 'mensaje' => string, 'codigo' => int HTTP]
    public function registrarReporte($preguntaId, $usuarioId, $partidaId, $motivo = null)
    {
        $pdo = $this->database->getConexion();

        if ($preguntaId <= 0) {
            return ['ok' => false, 'mensaje' => 'La pregunta no es válida.', 'codigo' => 400];
        }

        // La pregunta tiene que pertenecer a la partida activa del jugador
        $consulta = $pdo->prepare(
            "SELECT 1
             FROM partida_preguntas
             WHERE partida_id = :partida_id AND pregunta_id = :pregunta_id
             LIMIT 1"
        );
        $consulta->execute([
            'partida_id' => $partidaId,
            'pregunta_id' => $preguntaId
        ]);

        if (!$consulta->fetchColumn()) {
            return ['ok' => false, 'mensaje' => 'No podés reportar una pregunta que no está en tu partida.', 'codigo' => 403];
        }

        // Si ya la reportó, no se duplica
        $consulta = $pdo->prepare(
            "SELECT 1
             FROM reportes_preguntas
             WHERE pregunta_id = :pregunta_id AND usuario_id = :usuario_id
             LIMIT 1"
        );
        $consulta->execute([
            'pregunta_id' => $preguntaId,
            'usuario_id' => $usuarioId
        ]);

        if ($consulta->fetchColumn()) {
            return [
                'ok' => true,
                'mensaje' => 'Ya reportaste esta pregunta. Está pendiente de revisión.',
                'codigo' => 200
            ];
        }

        $motivo = trim((string) $motivo);
        $motivo = $motivo === '' ? null : mb_substr($motivo, 0, 255);

        try {
            $consulta = $pdo->prepare(
                "INSERT INTO reportes_preguntas (pregunta_id, usuario_id, partida_id, motivo, estado)
                 VALUES (:pregunta_id, :usuario_id, :partida_id, :motivo, 'pendiente')"
            );
            $consulta->execute([
                'pregunta_id' => $preguntaId,
                'usuario_id' => $usuarioId,
                'partida_id' => $partidaId,
                'motivo' => $motivo
            ]);
        } catch (\PDOException $error) {
            // 1062: clave duplicada (doble click / dos pestañas a la vez)
            if (($error->errorInfo[1] ?? null) == 1062) {
                return [
                    'ok' => true,
                    'mensaje' => 'Ya reportaste esta pregunta. Está pendiente de revisión.',
                    'codigo' => 200
                ];
            }
            throw $error;
        }

        return [
            'ok' => true,
            'mensaje' => 'Gracias, recibimos tu reporte. Un editor revisará la pregunta.',
            'codigo' => 200
        ];
    }
}