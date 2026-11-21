<?php

namespace app\model;

use DomainException;
use PDO;
use Throwable;

class CategoriaSugeridaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    // Siempre devuelve el resultado, un mensaje y el nombre preparado para guardar.
    public function validarNombreCategoriaSugerida(string $nombre): array
    {
        $nombrePreparado = preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', ' ', $nombre);
        $resultado = [
            'esValido' => false,
            'mensaje' => 'Ingresá un nombre con caracteres válidos.',
            'nombre' => ''
        ];

        if (is_string($nombrePreparado)) {
            $resultado['nombre'] = trim($nombrePreparado);

            if (strlen($resultado['nombre']) > 0) {
                // Cuenta caracteres Unicode: una letra con tilde ocupa un carácter.
                if (preg_match('/^.{1,50}$/usD', $resultado['nombre']) === 1) {
                    $resultado['esValido'] = true;
                    $resultado['mensaje'] = 'El nombre es válido.';
                } else {
                    $resultado['mensaje'] = 'El nombre puede tener hasta 50 caracteres.';
                }
            } else {
                $resultado['mensaje'] = 'El nombre de la categoría es obligatorio.';
            }
        }

        return $resultado;
    }

    public function existeCategoriaConNombre(string $nombre): bool
    {
        $consulta = $this->database->getConexion()->prepare(
            'SELECT EXISTS(
                SELECT 1 FROM categorias
                WHERE CONVERT(TRIM(nombre) USING utf8mb4) COLLATE utf8mb4_unicode_ci = :nombre
            )'
        );
        $consulta->execute(['nombre' => $nombre]);

        return (bool) $consulta->fetchColumn();
    }

    public function existeSugerenciaCategoriaPendienteConNombre(string $nombre): bool
    {
        $consulta = $this->database->getConexion()->prepare(
            "SELECT EXISTS(
                SELECT 1 FROM categoriasSugeridas
                WHERE nombre = :nombre AND estado = 'pendiente'
            )"
        );
        $consulta->execute(['nombre' => $nombre]);

        return (bool) $consulta->fetchColumn();
    }

    // Devuelve el ID creado. Si hay una validación pendiente, explica el motivo.
    public function guardarSugerenciaCategoria(int $usuarioId, string $nombre): int
    {
        $validacion = $this->validarNombreCategoriaSugerida($nombre);

        if ($validacion['esValido']) {
            $nombre = $validacion['nombre'];
        } else {
            throw new DomainException($validacion['mensaje']);
        }

        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();

        try {
            $this->bloquearCategoriasParaEvitarDuplicados();

            if ($this->existeCategoriaConNombre($nombre)) {
                throw new DomainException('Ya existe una categoría con ese nombre.');
            }

            if ($this->existeSugerenciaCategoriaPendienteConNombre($nombre)) {
                throw new DomainException('Ya hay una sugerencia pendiente con ese nombre.');
            }

            $consulta = $pdo->prepare(
                "INSERT INTO categoriasSugeridas (usuario_id, nombre, estado)
                 VALUES (:usuario_id, :nombre, 'pendiente')"
            );
            $consulta->execute(['usuario_id' => $usuarioId, 'nombre' => $nombre]);
            $idSugerencia = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $idSugerencia;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function obtenerCategoriasSugeridasPendientes(): array
    {
        $consulta = $this->database->getConexion()->prepare(
            "SELECT s.id, s.nombre, u.nombre_usuario AS autor,
                    DATE_FORMAT(s.fecha_sugerencia, '%d/%m/%Y %H:%i') AS fecha
             FROM categoriasSugeridas s
             INNER JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.estado = 'pendiente'
             ORDER BY s.fecha_sugerencia DESC, s.id DESC"
        );
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aprobarCategoriaSugerida(int $idSugerencia, int $editorId): bool
    {
        $pdo = $this->database->getConexion();
        $pdo->beginTransaction();

        try {
            $this->bloquearCategoriasParaEvitarDuplicados();
            $consulta = $pdo->prepare(
                'SELECT nombre, estado FROM categoriasSugeridas WHERE id = :id FOR UPDATE'
            );
            $consulta->execute(['id' => $idSugerencia]);
            $sugerencia = $consulta->fetch(PDO::FETCH_ASSOC);

            if (is_array($sugerencia) && $sugerencia['estado'] === 'pendiente') {
                $validacion = $this->validarNombreCategoriaSugerida($sugerencia['nombre']);
            } else {
                throw new DomainException('La sugerencia no existe o ya fue revisada.');
            }

            if ($validacion['esValido']) {
                $nombre = $validacion['nombre'];
            } else {
                throw new DomainException($validacion['mensaje']);
            }

            if ($this->existeCategoriaConNombre($nombre)) {
                throw new DomainException('Ya existe una categoría con ese nombre. Podés rechazar esta sugerencia.');
            }

            // Recién al aprobar se incorpora a las categorías utilizadas por el juego.
            $consulta = $pdo->prepare('INSERT INTO categorias (nombre, color, habilitada) VALUES (:nombre, :color, 1)');
            $consulta->execute(['nombre' => $nombre, 'color' => '#7c4ee6']);

            $consulta = $pdo->prepare(
                "UPDATE categoriasSugeridas
                 SET estado = 'aceptada', editor_id = :editor_id, fecha_revision = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $consulta->execute(['editor_id' => $editorId, 'id' => $idSugerencia]);
            $pdo->commit();

            return true;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public function rechazarCategoriaSugerida(int $idSugerencia, int $editorId): bool
    {
        $consulta = $this->database->getConexion()->prepare(
            "UPDATE categoriasSugeridas
             SET estado = 'rechazada', editor_id = :editor_id, fecha_revision = CURRENT_TIMESTAMP
             WHERE id = :id AND estado = 'pendiente'"
        );
        $consulta->execute(['editor_id' => $editorId, 'id' => $idSugerencia]);

        if ($consulta->rowCount() === 1) {
            return true;
        } else {
            throw new DomainException('La sugerencia no existe o ya fue revisada.');
        }
    }

    private function bloquearCategoriasParaEvitarDuplicados(): bool
    {
        // Guardar y aprobar toman el mismo bloqueo hasta el commit.
        // Así, dos pedidos simultáneos no pueden crear nombres duplicados.
        $consulta = $this->database->getConexion()->prepare('SELECT id FROM categorias ORDER BY id FOR UPDATE');

        return $consulta->execute();
    }
}
