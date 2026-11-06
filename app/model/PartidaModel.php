<?php

namespace app\model;

use app\model\enums\EstadoPartida;
use PDO;

class PartidaModel
{
    private const DURACION_PREGUNTA = 15;
    private const MOTIVO_TIEMPO_AGOTADO = 'tiempo_agotado';

    private $database;
    private $preguntaModel;
    private $usuarioModel;

    public function __construct($database, $preguntaModel, $usuarioModel)
    {
        $this->database = $database;
        $this->preguntaModel = $preguntaModel;
        $this->usuarioModel = $usuarioModel;
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
        $nivel = $this->usuarioModel->obtenerNivel($idUsuario);

        $sql = "INSERT INTO partidas (usuario_id, estado, puntaje, nivel_inicial)
        VALUES (:usuario_id, :estado, 0, :nivel)";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'usuario_id' => $idUsuario,
            'estado' => $estadoInicial->value,
            'nivel' => $nivel
        ]);

        return $errores;
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
               c.color AS color,
               TIMESTAMPDIFF(MICROSECOND, CURRENT_TIMESTAMP(6),
                   COALESCE(pp.vence_en, DATE_ADD(pp.fecha_creacion, INTERVAL :duracion SECOND))
               ) AS microsegundos_restantes
        FROM partida_preguntas pp
        INNER JOIN preguntas p ON pp.pregunta_id = p.id
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE pp.partida_id = :partida_id
          AND pp.fecha_respuesta IS NULL
        ORDER BY pp.id DESC
        LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'partida_id' => $partidaId,
            'duracion' => self::DURACION_PREGUNTA
        ]);
        $pregunta = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$pregunta) {
            return null;
        }

        $tiempoRestanteMs = max(0, (int) ceil($pregunta['microsegundos_restantes'] / 1000));

        return [
            'id' => (int) $pregunta['pregunta_id'],
            'idAsignacion' => (int) $pregunta['partida_pregunta_id'],
            'tiempoRestanteMs' => $tiempoRestanteMs,
            'duracionPreguntaMs' => self::DURACION_PREGUNTA * 1000,
            'tiempoRestante' => (int) ceil($tiempoRestanteMs / 1000),
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
        $dificultad = $this->dificultadParaNivel($this->obtenerNivelInicial($partidaId));

        // Backend: Seleccionar una pregunta habilitada
        // que no haya sido respondida ni entregada aún en esta partida
        $sql = "SELECT p.id, p.pregunta, c.nombre AS categoria, c.color AS color
        FROM preguntas p
        INNER JOIN categorias c ON p.categoria_id = c.id
        WHERE p.habilitada = 1
          AND p.id NOT IN (
              SELECT pregunta_id FROM partida_preguntas WHERE partida_id = :partida_id
          )
        ORDER BY (p.dificultad = :dificultad) DESC, RAND()
        LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'partida_id' => $partidaId,
            'dificultad' => $dificultad
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
                    (partida_id, pregunta_id, numero_pregunta, vence_en)
                  VALUES
                    (:partida_id, :pregunta_id, :numero_pregunta,
                     DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :duracion SECOND))";

        $consultaInsert = $pdo->prepare($sqlInsert);

        $consultaInsert->execute([
            'partida_id' => $partidaId,
            'pregunta_id' => $pregunta['id'],
            'numero_pregunta' => $numeroPregunta,
            'duracion' => self::DURACION_PREGUNTA
        ]);

        return $this->obtenerPreguntaActual($partidaId);
    }

    public function prepararPregunta(int $partidaId, int $idUsuario): array
    {
        return $this->enTransaccion(function (PDO $pdo) use ($partidaId, $idUsuario) {
            $partida = $this->bloquearPartida($pdo, $partidaId, $idUsuario);
            if (!$this->estaActiva($partida)) {
                return $this->resultadoPartidaCerrada($partida);
            }

            $pregunta = $this->obtenerPreguntaActual($partidaId);
            if ($pregunta && $pregunta['tiempoRestanteMs'] === 0) {
                return $this->cerrarPorTiempo($pdo, $partida, $pregunta['idAsignacion']);
            }

            return [
                'pregunta' => $pregunta ?? $this->obtenerSiguientePregunta($partidaId),
                'puntaje' => (int) $partida['puntaje']
            ];
        });
    }

    public function vencerPregunta(int $idUsuario, int $idAsignacion): array
    {
        return $this->conPreguntaPendiente(
            $idUsuario,
            $idAsignacion,
            function (PDO $pdo, array $partida, array $pregunta): array {
                if ($pregunta['tiempoRestanteMs'] > 0) {
                    return [
                        'tiempoAgotado' => false,
                        'idAsignacion' => $pregunta['idAsignacion'],
                        'tiempoRestanteMs' => $pregunta['tiempoRestanteMs']
                    ];
                }

                return $this->cerrarPorTiempo($pdo, $partida, $pregunta['idAsignacion']);
            }
        );
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

    public function responderPregunta(int $idUsuario, int $idAsignacion, int $respuesta): array
    {
        return $this->conPreguntaPendiente(
            $idUsuario,
            $idAsignacion,
            function (PDO $pdo, array $partida, array $pregunta) use ($respuesta): array {
                if ($pregunta['tiempoRestanteMs'] === 0) {
                    return $this->cerrarPorTiempo($pdo, $partida, $pregunta['idAsignacion']);
                }

                return $this->registrarRespuesta($pdo, $partida, $pregunta, $respuesta);
            }
        );
    }

    private function registrarRespuesta(PDO $pdo, array $partida, array $pregunta, int $respuesta): array
    {
        $partidaId = (int) $partida['id'];
        $idUsuario = (int) $partida['usuario_id'];
        $preguntaId = $pregunta['id'];

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

        $sqlUpdatePP = "UPDATE partida_preguntas
                        SET opcion_id = :opcion_id,
                            es_correcta = :es_correcta,
                            fecha_respuesta = NOW()
                        WHERE id = :id
                          AND fecha_respuesta IS NULL
                          AND COALESCE(vence_en, DATE_ADD(fecha_creacion, INTERVAL :duracion SECOND))
                              > CURRENT_TIMESTAMP(6)";
        $consultaUpdatePP = $pdo->prepare($sqlUpdatePP);
        $consultaUpdatePP->execute([
            'opcion_id' => $opcionElegida['id'],
            'es_correcta' => $esCorrecta ? 1 : 0,
            'id' => $pregunta['idAsignacion'],
            'duracion' => self::DURACION_PREGUNTA
        ]);

        if ($consultaUpdatePP->rowCount() === 0) {
            return $this->cerrarPorTiempo($pdo, $partida, $pregunta['idAsignacion']);
        }

        if ($esCorrecta) {
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
            //Respuesta incorrecta — finaliza la partida
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
        $pdo = $this->database->getConexion();
        $sql = "UPDATE usuarios
                    SET puntaje_acumulado = puntaje_acumulado + 1
                    WHERE id = :id";
        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $idUsuario]);
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
        $this->cerrarPartida($partidaId, EstadoPartida::FINALIZADA);
    }

    public function abandonarPartida($idUsuario)
    {
        $partida = $this->obtenerPartidaActiva($idUsuario);

        if ($partida) {
            $this->cerrarPartida($partida['id'], EstadoPartida::ABANDONADA);
        }
    }

    // Único punto de cierre: se ejecuta UNA sola vez por partida
    private function cerrarPartida($partidaId, EstadoPartida $estadoFinal, ?string $motivo = null)
    {
        $this->enTransaccion(function (PDO $pdo) use ($partidaId, $estadoFinal, $motivo) {
            $sql = "UPDATE partidas
                SET estado = :estado, motivo_fin = :motivo
                WHERE id = :id
                  AND estado IN (:creada, :en_curso)";
            $consulta = $pdo->prepare($sql);
            $consulta->execute([
                'estado' => $estadoFinal->value,
                'motivo' => $motivo,
                'id' => $partidaId,
                'creada' => EstadoPartida::CREADA->value,
                'en_curso' => EstadoPartida::EN_CURSO->value
            ]);

            // Si rowCount es 0, la partida ya estaba cerrada: no se recuenta nada
            if ($consulta->rowCount() === 1) {
                $this->preguntaModel->recalcularDificultadDePartida($partidaId);

                // Abandonada: cuenta para las preguntas, pero no cambia el nivel del jugador
                if ($estadoFinal === EstadoPartida::FINALIZADA) {
                    $idUsuario = $this->obtenerUsuarioDePartida($partidaId);
                    $this->usuarioModel->recalcularNivel($idUsuario);
                }
            }

        });
    }

    private function conPreguntaPendiente(int $idUsuario, int $idAsignacion, callable $operacion): array
    {
        $pdo = $this->database->getConexion();
        $consulta = $pdo->prepare("SELECT pp.partida_id
                                  FROM partida_preguntas pp
                                  INNER JOIN partidas p ON p.id = pp.partida_id
                                  WHERE pp.id = :asignacion AND p.usuario_id = :usuario");
        $consulta->execute(['asignacion' => $idAsignacion, 'usuario' => $idUsuario]);
        $partidaId = $consulta->fetchColumn();
        if ($partidaId === false) {
            return ['error' => 'La pregunta no corresponde a tu partida.'];
        }

        return $this->enTransaccion(function (PDO $pdo) use ($partidaId, $idUsuario, $idAsignacion, $operacion): array {
            $partida = $this->bloquearPartida($pdo, (int) $partidaId, $idUsuario);
            if (!$this->estaActiva($partida)) {
                return $this->resultadoPartidaCerrada($partida);
            }

            $pregunta = $this->obtenerPreguntaActual($partidaId);
            if (!$pregunta || $pregunta['idAsignacion'] !== $idAsignacion) {
                return ['error' => 'Esa pregunta ya no está pendiente.', 'ya_respondida' => true];
            }

            return $operacion($pdo, $partida, $pregunta);
        });
    }

    private function enTransaccion(callable $operacion)
    {
        $pdo = $this->database->getConexion();
        if ($pdo->inTransaction()) {
            return $operacion($pdo);
        }

        $pdo->beginTransaction();
        try {
            $resultado = $operacion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function bloquearPartida(PDO $pdo, int $partidaId, int $idUsuario): ?array
    {
        $consulta = $pdo->prepare("SELECT id, usuario_id, estado, puntaje, motivo_fin
                                  FROM partidas
                                  WHERE id = :id AND usuario_id = :usuario
                                  FOR UPDATE");
        $consulta->execute(['id' => $partidaId, 'usuario' => $idUsuario]);
        return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function estaActiva(?array $partida): bool
    {
        return in_array($partida['estado'] ?? null, [
            EstadoPartida::CREADA->value,
            EstadoPartida::EN_CURSO->value
        ], true);
    }

    private function cerrarPorTiempo(PDO $pdo, array $partida, int $idAsignacion): array
    {
        $consulta = $pdo->prepare("UPDATE partida_preguntas
                                  SET es_correcta = 0, fecha_respuesta = CURRENT_TIMESTAMP
                                  WHERE id = :asignacion AND partida_id = :partida
                                    AND fecha_respuesta IS NULL");
        $consulta->execute(['asignacion' => $idAsignacion, 'partida' => $partida['id']]);

        $this->cerrarPartida($partida['id'], EstadoPartida::FINALIZADA, self::MOTIVO_TIEMPO_AGOTADO);
        return $this->resultadoTiempoAgotado((int) $partida['puntaje']);
    }

    private function resultadoPartidaCerrada(?array $partida): array
    {
        if (($partida['motivo_fin'] ?? null) === self::MOTIVO_TIEMPO_AGOTADO) {
            return $this->resultadoTiempoAgotado((int) $partida['puntaje']);
        }

        return ['error' => 'No tenés una partida activa en este momento.'];
    }

    private function resultadoTiempoAgotado(int $puntaje): array
    {
        return [
            'correcta' => false,
            'finalizada' => true,
            'tiempoAgotado' => true,
            'mensaje' => 'Se te acabó el tiempo.',
            'puntaje' => $puntaje,
            'respuestasCorrectas' => $puntaje
        ];
    }

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

    private function obtenerNivelInicial($partidaId)
    {
        $pdo = $this->database->getConexion();
        $consulta = $pdo->prepare("SELECT nivel_inicial FROM partidas WHERE id = :id LIMIT 1");
        $consulta->execute(['id' => $partidaId]);
        return $consulta->fetchColumn() ?: 'medio';
    }

    private function obtenerUsuarioDePartida($partidaId)
    {
        $pdo = $this->database->getConexion();
        $consulta = $pdo->prepare("SELECT usuario_id FROM partidas WHERE id = :id LIMIT 1");
        $consulta->execute(['id' => $partidaId]);
        return (int) $consulta->fetchColumn();
    }

    private function dificultadParaNivel($nivel)
    {
        return ['bajo' => 'facil', 'medio' => 'media', 'alto' => 'dificil'][$nivel] ?? 'media';
    }

}
