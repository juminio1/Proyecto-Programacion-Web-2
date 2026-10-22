<?php

namespace app\model;
use PDO;

class InicioModel
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }


    public function buscarUsuarioPorId($id)
    {
        $pdo = $this->database->getConexion();

        $sql = "SELECT id, nombre_completo, nombre_usuario, email
            FROM usuarios
            WHERE id = :id
            LIMIT 1";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $id]);

        return $consulta->fetch(PDO::FETCH_ASSOC);
    }


}
