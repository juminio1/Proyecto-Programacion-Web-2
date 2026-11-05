<?php

namespace app\model;

use PDO;

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
                ORDER BY nombre ASC";

        $consulta = $pdo->prepare($sql);
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categoriaExiste(int $categoriaId): bool
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id
                FROM categorias
                WHERE id = :categoria_id
                LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute([
            'categoria_id' => $categoriaId
        ]);

        return $consulta->fetch(PDO::FETCH_ASSOC) !== false;
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