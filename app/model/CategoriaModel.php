<?php

namespace app\model;

use DomainException;
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

    public function crearCategoria(string $nombre, string $color): int
    {
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

        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();
        try {
            // Usa el mismo bloqueo que la aprobación de sugerencias de categorías.
            $pdo->query('SELECT id FROM categorias ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            $consulta = $pdo->prepare(
                'SELECT EXISTS(
                    SELECT 1 FROM categorias
                    WHERE CONVERT(TRIM(nombre) USING utf8mb4) COLLATE utf8mb4_unicode_ci = :nombre
                )'
            );
            $consulta->execute(['nombre' => $nombre]);
            if ((bool) $consulta->fetchColumn()) {
                throw new DomainException('Ya existe una categoría con ese nombre, aunque esté deshabilitada.');
            }

            $consulta = $pdo->prepare(
                'INSERT INTO categorias (nombre, color, habilitada) VALUES (:nombre, :color, 1)'
            );
            $consulta->execute(['nombre' => $nombre, 'color' => $color]);
            $id = (int) $pdo->lastInsertId();
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

    public function obtenerCategoriasHabilitadasConPreguntas(): array
    {
        // El LEFT JOIN conserva las categorías habilitadas que todavía están vacías.
        $consulta = $this->database->getConexion()->prepare(
            'SELECT c.id, c.nombre, c.color, COUNT(p.id) AS cantidad_preguntas
             FROM categorias c
             LEFT JOIN preguntas p ON p.categoria_id = c.id AND p.habilitada = 1
             WHERE c.habilitada = 1
             GROUP BY c.id, c.nombre, c.color
             ORDER BY c.nombre ASC'
        );
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
