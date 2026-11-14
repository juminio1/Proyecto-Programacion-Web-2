<?php

namespace app\model;

use DomainException;
use PDO;
use Throwable;

class EditorModel
{
    private PDO $pdo;

    public function __construct($database)
    {
        $this->pdo = $database->getConexion();
    }

    public function obtenerSugerencias(): array
    {
        return $this->pdo->query(
            "SELECT s.*, c.nombre AS categoria, u.nombre_usuario
             FROM preguntasSugeridas s
             JOIN categorias c ON c.id = s.categoria_id
             JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.estado = 'pendiente'
             ORDER BY s.fecha_sugerencia, s.id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerReportes(): array
    {
        return $this->pdo->query(
            "SELECT r.*, p.pregunta, c.nombre AS categoria, u.nombre_usuario
             FROM reportes_preguntas r
             JOIN preguntas p ON p.id = r.pregunta_id
             JOIN categorias c ON c.id = p.categoria_id
             JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.estado = 'pendiente'
             ORDER BY r.fecha_reporte, r.id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPreguntas(string $buscar, int $pagina): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT p.id, p.pregunta, p.habilitada, c.nombre AS categoria
             FROM preguntas p JOIN categorias c ON c.id = p.categoria_id
             WHERE p.reemplazada_por IS NULL
               AND (:vacia = '' OR LOCATE(:buscar, p.pregunta) > 0)
             ORDER BY p.id DESC
             LIMIT 21 OFFSET :salto"
        );
        $consulta->bindValue('vacia', $buscar);
        $consulta->bindValue('buscar', $buscar);
        $consulta->bindValue('salto', ($pagina - 1) * 20, PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPregunta(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, pregunta, categoria_id, habilitada FROM preguntas WHERE id = ? AND reemplazada_por IS NULL'
        );
        $consulta->execute([$id]);
        $pregunta = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($pregunta === false) {
            return null;
        }

        $consulta = $this->pdo->prepare(
            'SELECT letra, texto, es_correcta FROM opciones WHERE pregunta_id = ? ORDER BY id'
        );
        $consulta->execute([$id]);
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $opcion) {
            $pregunta['opcion_' . strtolower($opcion['letra'])] = $opcion['texto'];
            if ($opcion['es_correcta']) {
                $pregunta['respuesta_correcta'] = $opcion['letra'];
            }
        }
        return $pregunta;
    }

    public function obtenerCategorias(): array
    {
        return $this->pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarPregunta(?int $id, array $datos): void
    {
        $this->transaccion(function () use ($id, $datos) {
            $habilitada = 1;
            $origen = null;
            if ($id !== null) {
                $consulta = $this->pdo->prepare(
                    'SELECT habilitada, pregunta_origen_id FROM preguntas WHERE id = ? AND reemplazada_por IS NULL FOR UPDATE'
                );
                $consulta->execute([$id]);
                $resultado = $consulta->fetch(PDO::FETCH_ASSOC);
                if ($resultado === false) {
                    throw new DomainException('La pregunta cambió desde que la abriste. Volvé al listado.');
                }
                $habilitada = (int) $resultado['habilitada'];
                $origen = (int) ($resultado['pregunta_origen_id'] ?? $id);
            }

            $nuevoId = $this->insertarPregunta($datos, $habilitada, $origen);
            if ($id !== null) {
                // Las partidas en curso e históricas conservan la pregunta y las opciones originales.
                $consulta = $this->pdo->prepare('UPDATE preguntas SET habilitada = 0, reemplazada_por = ? WHERE id = ?');
                $consulta->execute([$nuevoId, $id]);
            }
        });
    }

    public function cambiarDisponibilidad(int $id, bool $habilitada): void
    {
        $consulta = $this->pdo->prepare(
            'UPDATE preguntas SET habilitada = ? WHERE id = ? AND reemplazada_por IS NULL AND habilitada <> ?'
        );
        $valor = (int) $habilitada;
        $consulta->execute([$valor, $id, $valor]);
        if ($consulta->rowCount() !== 1) {
            throw new DomainException('La pregunta ya tiene ese estado o fue reemplazada.');
        }
    }

    public function resolverSugerencia(int $id, bool $aceptar): void
    {
        $this->transaccion(function () use ($id, $aceptar) {
            $consulta = $this->pdo->prepare("SELECT * FROM preguntasSugeridas WHERE id = ? AND estado = 'pendiente' FOR UPDATE");
            $consulta->execute([$id]);
            $datos = $consulta->fetch(PDO::FETCH_ASSOC);
            if ($datos === false) {
                throw new DomainException('La sugerencia no existe o ya fue revisada.');
            }
            if ($aceptar) {
                $this->insertarPregunta($datos, 1);
            }
            $consulta = $this->pdo->prepare('UPDATE preguntasSugeridas SET estado = ? WHERE id = ?');
            $consulta->execute([$aceptar ? 'aceptada' : 'rechazada', $id]);
        });
    }

    public function resolverReporte(int $id, int $editorId, bool $aprobar): void
    {
        $this->transaccion(function () use ($id, $editorId, $aprobar) {
            $consulta = $this->pdo->prepare("SELECT pregunta_id FROM reportes_preguntas WHERE id = ? AND estado = 'pendiente' FOR UPDATE");
            $consulta->execute([$id]);
            $idPregunta = $consulta->fetchColumn();
            if ($idPregunta === false) {
                throw new DomainException('El reporte no existe o ya fue revisado.');
            }
            if ($aprobar) {
                $consulta = $this->pdo->prepare('UPDATE preguntas SET habilitada = 0 WHERE id = ?');
                $consulta->execute([$idPregunta]);
            }
            $consulta = $this->pdo->prepare(
                'UPDATE reportes_preguntas SET estado = ?, fecha_revision = CURRENT_TIMESTAMP, editor_id = ? WHERE id = ?'
            );
            $consulta->execute([$aprobar ? 'aprobado' : 'rechazado', $editorId, $id]);
        });
    }

    private function insertarPregunta(array $datos, int $habilitada, ?int $origen = null): int
    {
        if (!in_array($datos['respuesta_correcta'], ['A', 'B', 'C', 'D'], true)) {
            throw new DomainException('Elegí una respuesta correcta válida.');
        }

        $opciones = [];
        foreach (['A', 'B', 'C', 'D'] as $letra) {
            $opciones[$letra] = trim($datos['opcion_' . strtolower($letra)]);
        }
        if (in_array('', $opciones, true) || count(array_unique(array_map('mb_strtolower', $opciones))) !== 4) {
            throw new DomainException('La pregunta debe tener cuatro respuestas distintas y completas.');
        }

        $consulta = $this->pdo->prepare('INSERT INTO preguntas (categoria_id, pregunta, habilitada, pregunta_origen_id) VALUES (?, ?, ?, ?)');
        $consulta->execute([$datos['categoria_id'], $datos['pregunta'], $habilitada, $origen]);
        $id = (int) $this->pdo->lastInsertId();

        $consulta = $this->pdo->prepare('INSERT INTO opciones (pregunta_id, letra, texto, es_correcta) VALUES (?, ?, ?, ?)');
        foreach ($opciones as $letra => $texto) {
            $consulta->execute([$id, $letra, $texto, (int) ($letra === $datos['respuesta_correcta'])]);
        }
        return $id;
    }

    private function transaccion(callable $operacion): void
    {
        $this->pdo->beginTransaction();
        try {
            $operacion();
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
