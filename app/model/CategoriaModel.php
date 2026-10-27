
<?php

namespace app\model;

class CategoriaModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function obtenerCategorias()
    {
        $sql = "SELECT id, nombre, color
                FROM categorias
                ORDER BY nombre ASC";

        return $this->database->queryAll($sql);
    }
}
