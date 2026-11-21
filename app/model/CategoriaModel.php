<?php

namespace app\model;

use PDO;

class CategoriaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerCategoriasHabilitadasConPreguntas(): array
    {
        // El LEFT JOIN conserva las categorías habilitadas que todavía están vacías.
        $consulta = $this->database->getConexion()->prepare(
            'SELECT c.id, c.nombre, COUNT(p.id) AS cantidad_preguntas
             FROM categorias c
             LEFT JOIN preguntas p ON p.categoria_id = c.id AND p.habilitada = 1
             WHERE c.habilitada = 1
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre ASC'
        );
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
