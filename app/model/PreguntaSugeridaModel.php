<?php

namespace app\model;

use DomainException;
use PDO;
use Throwable;

class PreguntaSugeridaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerCategorias(): array
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, nombre
                FROM categorias
                WHERE habilitada = 1
                ORDER BY nombre ASC";

        $consulta = $pdo->prepare($sql);
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categoriaExiste(int $categoriaId, bool $bloquear = false): bool
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM categorias
                WHERE id = :categoria_id
                  AND habilitada = 1
                LIMIT 1";

        if ($bloquear) {
            $sql .= " LOCK IN SHARE MODE";
        }

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'categoria_id' => $categoriaId
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function obtenerPendientes(): array
    {
        $pdo = $this->database->getConexion();
        $sql = "SELECT s.id, s.pregunta, s.opcion_a, s.opcion_b, s.opcion_c, s.opcion_d,
                       s.respuesta_correcta, c.nombre AS categoria
                FROM preguntasSugeridas s
                JOIN categorias c ON c.id = s.categoria_id
                WHERE s.estado = 'pendiente'
                ORDER BY s.fecha_sugerencia DESC, s.id DESC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aprobar(int $idSugerencia): void
    {
        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();

        try {
            // La aprobación y el rechazo compiten por el bloqueo de esta misma fila.
            $consulta = $pdo->prepare(
                'SELECT categoria_id, pregunta, opcion_a, opcion_b, opcion_c, opcion_d, respuesta_correcta, estado
                 FROM preguntasSugeridas WHERE id = ? FOR UPDATE'
            );
            $consulta->execute([$idSugerencia]);
            $sugerencia = $consulta->fetch(PDO::FETCH_ASSOC);

            if ($sugerencia === false || $sugerencia['estado'] !== 'pendiente') {
                throw new DomainException('La sugerencia no existe o ya fue revisada.');
            }

            if (!$this->categoriaExiste((int) $sugerencia['categoria_id'], true)) {
                throw new DomainException('La categoría de esta pregunta ya no está habilitada.');
            }

            $pregunta = trim($sugerencia['pregunta']);
            $correcta = strtoupper(trim($sugerencia['respuesta_correcta']));
            $opciones = [];
            foreach (['A', 'B', 'C', 'D'] as $letra) {
                $opciones[$letra] = trim($sugerencia['opcion_' . strtolower($letra)]);
            }

            $normalizar = function_exists('mb_strtolower')
                ? static fn (string $texto): string => mb_strtolower($texto, 'UTF-8')
                : 'strtolower';

            if ($pregunta === ''
                || !isset($opciones[$correcta])
                || in_array('', $opciones, true)
                || count(array_unique(array_map($normalizar, $opciones))) !== 4) {
                throw new DomainException('La sugerencia debe tener un enunciado, cuatro opciones distintas y una respuesta correcta válida.');
            }

            $consulta = $pdo->prepare(
                'INSERT INTO preguntas (categoria_id, pregunta, habilitada) VALUES (?, ?, 1)'
            );
            $consulta->execute([(int) $sugerencia['categoria_id'], $pregunta]);
            $idPregunta = (int) $pdo->lastInsertId();

            $consulta = $pdo->prepare(
                'INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (?, ?, ?, ?)'
            );
            foreach ($opciones as $letra => $texto) {
                $consulta->execute([$idPregunta, $letra, $texto, (int) ($letra === $correcta)]);
            }

            $consulta = $pdo->prepare("UPDATE preguntasSugeridas SET estado = 'aceptada' WHERE id = ?");
            $consulta->execute([$idSugerencia]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function rechazar(int $idSugerencia): void
    {
        $pdo = $this->database->getConexion();
        $consulta = $pdo->prepare(
            "UPDATE preguntasSugeridas SET estado = 'rechazada' WHERE id = ? AND estado = 'pendiente'"
        );
        $consulta->execute([$idSugerencia]);

        if ($consulta->rowCount() !== 1) {
            throw new DomainException('La sugerencia no existe o ya fue revisada.');
        }
    }

    public function guardarSugerencia(
        int $usuarioId,
        int $categoriaId,
        string $pregunta,
        string $opcionA,
        string $opcionB,
        string $opcionC,
        string $opcionD,
        string $respuestaCorrecta
    ): bool {
        $pdo = $this->database->getConexion();

        $sql = "INSERT INTO preguntasSugeridas (
                    usuario_id,
                    categoria_id,
                    pregunta,
                    opcion_a,
                    opcion_b,
                    opcion_c,
                    opcion_d,
                    respuesta_correcta,
                    estado
                )
                VALUES (
                    :usuario_id,
                    :categoria_id,
                    :pregunta,
                    :opcion_a,
                    :opcion_b,
                    :opcion_c,
                    :opcion_d,
                    :respuesta_correcta,
                    'pendiente'
                )";

        $consulta = $pdo->prepare($sql);

        return $consulta->execute([
            'usuario_id' => $usuarioId,
            'categoria_id' => $categoriaId,
            'pregunta' => $pregunta,
            'opcion_a' => $opcionA,
            'opcion_b' => $opcionB,
            'opcion_c' => $opcionC,
            'opcion_d' => $opcionD,
            'respuesta_correcta' => $respuestaCorrecta
        ]);
    }
}
