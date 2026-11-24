<?php

namespace app\model;

use DomainException;
use OutOfBoundsException;
use PDO;
use PDOException;
use Throwable;

class CategoriaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerCategoriaPorId(int $id): ?array
    {
        $consulta = $this->database->getConexion()->prepare(
            'SELECT id, nombre, color, habilitada FROM categorias WHERE id = :id'
        );
        $consulta->execute(['id' => $id]);
        return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerCategoriaParaEditar(int $id): array
    {
        $categoria = $this->obtenerCategoriaPorId($id);
        if ($categoria === null) {
            throw new OutOfBoundsException('La categoría seleccionada ya no existe.');
        }
        if (!(bool) $categoria['habilitada']) {
            throw new OutOfBoundsException('La categoría está deshabilitada y su edición está bloqueada.');
        }

        return $categoria;
    }

    public function crearCategoria(string $nombre, string $color): int
    {
        return $this->guardarDatosCategoria(null, $nombre, $color);
    }

    public function actualizarCategoria(int $id, string $nombre, string $color): void
    {
        $this->guardarDatosCategoria($id, $nombre, $color);
    }

    private function guardarDatosCategoria(?int $id, string $nombre, string $color): int
    {
        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();
        try {
            // Usa el mismo bloqueo que la aprobación de sugerencias de categorías.
            $pdo->query('SELECT id FROM categorias ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            if ($id !== null) {
                $this->obtenerCategoriaParaEditar($id);
            }

            $nombre = preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', ' ', $nombre);
            if (!is_string($nombre)) {
                throw new DomainException('Ingresá un nombre con caracteres válidos.');
            }
            $nombre = trim($nombre);
            if ($nombre === '') {
                throw new DomainException('El nombre de la categoría es obligatorio.');
            }
            if (preg_match('/\A[^\p{C}]{1,50}\z/u', $nombre) !== 1) {
                throw new DomainException('El nombre debe tener hasta 50 caracteres y no incluir caracteres de control.');
            }

            $color = strtoupper(trim($color));
            if ($color === '') {
                throw new DomainException('El color de la categoría es obligatorio.');
            }
            if (preg_match('/\A#[0-9A-F]{6}\z/', $color) !== 1) {
                throw new DomainException('Elegí un color válido con formato #RRGGBB.');
            }

            $consulta = $pdo->prepare(
                'SELECT EXISTS(
                    SELECT 1 FROM categorias
                    WHERE id <> :id_excluido
                      AND CONVERT(TRIM(nombre) USING utf8mb4) COLLATE utf8mb4_unicode_ci = :nombre
                )'
            );
            $consulta->execute(['nombre' => $nombre, 'id_excluido' => $id ?? 0]);
            if ((bool) $consulta->fetchColumn()) {
                throw new DomainException('Ya existe una categoría con ese nombre, aunque esté deshabilitada.');
            }

            if ($id === null) {
                $consulta = $pdo->prepare(
                    'INSERT INTO categorias (nombre, color, habilitada) VALUES (:nombre, :color, 1)'
                );
                $consulta->execute(['nombre' => $nombre, 'color' => $color]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $consulta = $pdo->prepare(
                    'UPDATE categorias SET nombre = :nombre, color = :color WHERE id = :id'
                );
                $consulta->execute(['nombre' => $nombre, 'color' => $color, 'id' => $id]);
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // El índice único también protege frente a altas simultáneas.
            if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Ya existe una categoría con ese nombre.', 0, $error);
            }
            throw $error;
        }
    }

    public function darDeBajaCategoria(int $id): void
    {
        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();
        try {
            $consulta = $pdo->prepare(
                'UPDATE categorias SET habilitada = 0 WHERE id = :id AND habilitada = 1'
            );
            $consulta->execute(['id' => $id]);
            if ($consulta->rowCount() === 0) {
                if ($this->obtenerCategoriaPorId($id) === null) {
                    throw new DomainException('La categoría seleccionada ya no existe.');
                }
                throw new DomainException('La categoría ya estaba deshabilitada.');
            }

            $consulta = $pdo->prepare(
                'UPDATE preguntas SET habilitada = 0 WHERE categoria_id = :id AND habilitada = 1'
            );
            $consulta->execute(['id' => $id]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function obtenerCategoriasParaGestion(): array
    {
        $consulta = $this->database->getConexion()->query(
            'SELECT c.id, c.nombre, c.color, c.habilitada, COUNT(p.id) AS cantidad_preguntas
             FROM categorias c
             LEFT JOIN preguntas p ON p.categoria_id = c.id AND p.habilitada = 1 AND c.habilitada = 1
             GROUP BY c.id, c.nombre, c.color, c.habilitada
             ORDER BY c.habilitada DESC, c.nombre ASC'
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
