<?php

namespace app\model;

use app\model\enums\EstadoPartida;
use PDO;

class PartidaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function crearPartida($idUsuario)
    {
        $errores = [];

        if (!$this->validarUsuarioExistente($idUsuario)) {
            return ["El usuario no existe."];
        }

        if ($this->validarPartidaExistente($idUsuario)) {
            return ["Ya tenés una partida creada o en curso."];
        }

        $pdo = $this->database->getConexion();

        $estadoInicial = EstadoPartida::CREADA;

        $sql = "INSERT INTO partidas (usuario_id, estado, puntaje)
                VALUES (:usuario_id, :estado, 0)";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'estado' => $estadoInicial->value
        ]);

        return $errores;
    }

    public function abandonarPartida($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = 'UPDATE partidas
            SET estado = :abandonada
            WHERE usuario_id = :usuario_id
              AND estado IN (:creada, :en_curso)';

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'abandonada' => EstadoPartida::ABANDONADA->value,
            'usuario_id' => $idUsuario,
            'creada' => EstadoPartida::CREADA->value,
            'en_curso' => EstadoPartida::EN_CURSO->value
        ]);
    }

    public function obtenerPartidaActiva($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, usuario_id, estado, puntaje, fecha_creacion
                FROM partidas
                WHERE usuario_id = :usuario_id
                  AND estado IN (:creada, :en_curso)
                ORDER BY id DESC
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'creada' => EstadoPartida::CREADA->value,
            'en_curso' => EstadoPartida::EN_CURSO->value
        ]);

        $partida = $consulta->fetch(PDO::FETCH_ASSOC);

        return $partida !== false ? $partida : null;
    }

    public function obtenerPreguntaActual($partidaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT pp.id AS partida_pregunta_id,
               pp.numero_pregunta,
               p.id AS pregunta_id,
               p.pregunta,
               c.nombre AS categoria,
               c.color AS color
        FROM partida_preguntas pp
        INNER JOIN preguntas p ON pp.pregunta_id = p.id
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE pp.partida_id = :partida_id
          AND pp.fecha_respuesta IS NULL
        ORDER BY pp.id DESC
        LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['partida_id' => $partidaId]);
        $pregunta = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$pregunta) {
            return null;
        }

        return [
            'id' => (int) $pregunta['pregunta_id'],
            'pregunta' => $pregunta['pregunta'],
            'categoria' => $pregunta['categoria'],
            'color' => $pregunta['color'],
            'numeroPregunta' => (int) $pregunta['numero_pregunta'],
            'opciones' => $this->obtenerOpcionesDePregunta($pregunta['pregunta_id'])
        ];
    }

    public function obtenerSiguientePregunta($partidaId)
    {
        $pdo = $this->database->getConexion();

        // Backend: Seleccionar una pregunta habilitada
        // que no haya sido respondida ni entregada aún en esta partida
        $sql = "SELECT p.id, p.pregunta, c.nombre AS categoria, c.color AS color
            FROM preguntas p
            INNER JOIN categorias c ON p.categoria_id = c.id
            WHERE p.habilitada = 1
              AND p.id NOT IN (
                  SELECT pregunta_id
                  FROM partida_preguntas
                  WHERE partida_id = :partida_id
              )
            ORDER BY RAND()
            LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'partida_id' => $partidaId
        ]);

        $pregunta = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$pregunta) {
            // No quedan más preguntas disponibles, finalizar la partida
            $this->finalizarPartida($partidaId);
            return null;
        }

        // Obtener correlativo de número de pregunta en la partida
        $sqlCount = "SELECT COUNT(*) AS total
                 FROM partida_preguntas
                 WHERE partida_id = :partida_id";

        $consultaCount = $pdo->prepare($sqlCount);

        $consultaCount->execute([
            'partida_id' => $partidaId
        ]);

        $totalAnteriores = (int) (
            $consultaCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
        );

        $numeroPregunta = $totalAnteriores + 1;

        // Registrar la asignación de la pregunta a la partida
        $sqlInsert = "INSERT INTO partida_preguntas
                    (partida_id, pregunta_id, numero_pregunta)
                  VALUES
                    (:partida_id, :pregunta_id, :numero_pregunta)";

        $consultaInsert = $pdo->prepare($sqlInsert);

        $consultaInsert->execute([
            'partida_id' => $partidaId,
            'pregunta_id' => $pregunta['id'],
            'numero_pregunta' => $numeroPregunta
        ]);

        return [
            'id' => (int) $pregunta['id'],
            'pregunta' => $pregunta['pregunta'],
            'categoria' => $pregunta['categoria'],
            'color' => $pregunta['color'],
            'numeroPregunta' => $numeroPregunta,
            'opciones' => $this->obtenerOpcionesDePregunta($pregunta['id'])
        ];
    }

    public function obtenerOpcionesDePregunta($preguntaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, letra, texto
                FROM opciones
                WHERE pregunta_id = :pregunta_id
                ORDER BY letra ASC, id ASC";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['pregunta_id' => $preguntaId]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function responderPregunta($partidaId, $idUsuario, $preguntaId, $respuesta)
    {
        $pdo = $this->database->getConexion();

        // 1. Verificar si la pregunta pertenece a esta partida
        $sqlPP = "SELECT id, opcion_id, es_correcta, fecha_respuesta
                  FROM partida_preguntas
                  WHERE partida_id = :partida_id AND pregunta_id = :pregunta_id
                  LIMIT 1";
        $consultaPP = $pdo->prepare($sqlPP);
        $consultaPP->execute([
            'partida_id' => $partidaId,
            'pregunta_id' => $preguntaId
        ]);
        $partidaPregunta = $consultaPP->fetch(PDO::FETCH_ASSOC);

        if (!$partidaPregunta) {
            return [
                'error' => 'La pregunta no corresponde a la partida en curso.',
                'correcta' => false
            ];
        }

        // 2. Backend: Evitar que una respuesta enviada nuevamente vuelva a sumar puntos.
        // 4 - Una misma respuesta no puede contabilizarse más de una vez.
        if (!empty($partidaPregunta['fecha_respuesta'])) {
            $puntaje = $this->obtenerPuntajePartida($partidaId);
            $resultado = [
                'ya_respondida' => true,
                'correcta' => (bool) $partidaPregunta['es_correcta'],
                'puntaje' => $puntaje,
                'respuestasCorrectas' => $puntaje
            ];

            // HU-09: Si la pregunta ya respondida fue incorrecta, la partida ya está finalizada.
            if (!$resultado['correcta']) {
                $resultado['finalizada'] = true;
                $resultado['mensaje'] = 'Esta partida ya finalizó.';
                $resultado['respuestaCorrecta'] = $this->obtenerRespuestaCorrecta($preguntaId);
            }

            return $resultado;
        }

        // 3. Backend: Comparar la opción elegida con la respuesta correcta.
        $sqlOpcion = "SELECT id, letra, texto, es_correcta
              FROM opciones
              WHERE pregunta_id = :pregunta_id
                AND id = :opcion_id
              LIMIT 1";

        $consultaOpcion = $pdo->prepare($sqlOpcion);

        $consultaOpcion->execute([
            'pregunta_id' => $preguntaId,
            'opcion_id' => (int) $respuesta
        ]);
        $opcionElegida = $consultaOpcion->fetch(PDO::FETCH_ASSOC);

        if (!$opcionElegida) {
            return [
                'error' => 'La opción seleccionada no es válida.',
                'correcta' => false
            ];
        }

        $esCorrecta = ((int) $opcionElegida['es_correcta'] === 1);

        // 4. Backend: Registrar la respuesta
        $sqlUpdatePP = "UPDATE partida_preguntas
                        SET opcion_id = :opcion_id,
                            es_correcta = :es_correcta,
                            fecha_respuesta = NOW()
                        WHERE id = :id";
        $consultaUpdatePP = $pdo->prepare($sqlUpdatePP);
        $consultaUpdatePP->execute([
            'opcion_id' => $opcionElegida['id'],
            'es_correcta' => $esCorrecta ? 1 : 0,
            'id' => $partidaPregunta['id']
        ]);

        if ($esCorrecta) {
            // 1 - Cada respuesta correcta suma exactamente un punto a la partida.
            // Backend: actualizar el puntaje de la partida.
            $sqlPuntos = "UPDATE partidas
                          SET puntaje = puntaje + 1,
                              estado = :estado
                          WHERE id = :partida_id";
            $consultaPuntos = $pdo->prepare($sqlPuntos);
            $consultaPuntos->execute([
                'estado' => EstadoPartida::EN_CURSO->value,
                'partida_id' => $partidaId
            ]);

            // 5 - Los puntos obtenidos se conservan para el puntaje acumulado del jugador.
            $this->sumarPuntoAcumuladoUsuario($idUsuario);

            $puntajeActual = $this->obtenerPuntajePartida($partidaId);

            // Backend: Seleccionar la siguiente pregunta.
            // 3 - Después de acertar, el jugador recibe otra pregunta.
            $siguientePregunta = $this->obtenerSiguientePregunta($partidaId);

            return [
                'correcta' => true,
                'puntaje' => $puntajeActual,
                'respuestasCorrectas' => $puntajeActual,
                'siguientePregunta' => $siguientePregunta
            ];
        } else {
            // HU-09: Respuesta incorrecta — finaliza la partida
            $this->finalizarPartida($partidaId);

            $puntajeActual = $this->obtenerPuntajePartida($partidaId);

            return [
                'correcta' => false,
                'finalizada' => true,
                'puntaje' => $puntajeActual,
                'respuestasCorrectas' => $puntajeActual,
                'mensaje' => 'Respuesta incorrecta. Fin de la partida.',
                'respuestaCorrecta' => $this->obtenerRespuestaCorrecta($preguntaId)
            ];
        }
    }

    public function obtenerPuntajePartida($partidaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT puntaje FROM partidas WHERE id = :id LIMIT 1";
        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $partidaId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['puntaje'] : 0;
    }

    public function sumarPuntoAcumuladoUsuario($idUsuario)
    {
        try {
            $pdo = $this->database->getConexion();
            $sql = "UPDATE usuarios
                    SET puntaje_acumulado = puntaje_acumulado + 1
                    WHERE id = :id";
            $consulta = $pdo->prepare($sql);
            $consulta->execute(['id' => $idUsuario]);
        } catch (\PDOException $e) {
            \Log::error('Error al actualizar puntaje acumulado en usuario: ' . $e->getMessage());
            error_log('Error al actualizar puntaje acumulado en usuario: ' . $e->getMessage());
        }
    }

    public function obtenerPuntajeAcumuladoUsuario($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT COALESCE(SUM(puntaje), 0) AS total
                FROM partidas
                WHERE usuario_id = :idUsuario";
        $consulta = $pdo->prepare($sql);
        $consulta->execute(['idUsuario' => $idUsuario]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int) $fila['total'] : 0;
    }

    public function finalizarPartida($partidaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "UPDATE partidas
                SET estado = :finalizada
                WHERE id = :partida_id";
        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'finalizada' => EstadoPartida::FINALIZADA->value,
            'partida_id' => $partidaId
        ]);
    }

    /**
     * HU-09: Obtiene la opción correcta de una pregunta (letra y texto).
     */
    public function obtenerRespuestaCorrecta($preguntaId)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT letra, texto
                FROM opciones
                WHERE pregunta_id = :pregunta_id
                  AND es_correcta = 1
                LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['pregunta_id' => $preguntaId]);
        $opcion = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$opcion) {
            return null;
        }

        return [
            'letra' => $opcion['letra'],
            'texto' => $opcion['texto']
        ];
    }

    private function validarPartidaExistente($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM partidas
                WHERE usuario_id = :usuario_id
                  AND estado IN (:creada, :en_curso)
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'creada' => EstadoPartida::CREADA->value,
            'en_curso' => EstadoPartida::EN_CURSO->value
        ]);

        $partidaEncontrada = $consulta->fetch(PDO::FETCH_ASSOC);

        return $partidaEncontrada !== false;
    }

    private function validarUsuarioExistente($idUsuario)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM usuarios
                WHERE id = :usuario_id
                LIMIT 1";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario
        ]);

        $usuarioEncontrado = $consulta->fetch(PDO::FETCH_ASSOC);

        return $usuarioEncontrado !== false;
    }
}